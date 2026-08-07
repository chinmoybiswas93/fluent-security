<?php

namespace FluentAuth\Tests\Unit;

use FluentAuth\App\Helpers\Helper;
use FluentAuth\App\Http\Controllers\SettingsController;
use FluentAuth\App\Http\Controllers\TwoFaController;
use FluentAuth\App\Services\TwoFa\TotpProvider;
use FluentAuth\App\Services\TwoFa\TotpTwoFaMethod;

/**
 * The administration side of two factor: saving a policy, and the panel that answers
 * "who has this turned on" and "get this person back in".
 */
class TwoFaAdminTest extends BaseTestCase
{
    private $admin;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->factory->user->create_and_get(['role' => 'administrator']);
        wp_set_current_user($this->admin->ID);

        $settings = Helper::getAuthSettings();
        $settings['totp_2fa'] = 'yes';
        $settings['totp_2fa_roles'] = [];
        $settings['totp_required_roles'] = [];
        $settings['email2fa'] = 'no';
        update_option('__fls_auth_settings', $settings);
        Helper::resetStatics();
    }

    public function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
    }

    private function save($overrides)
    {
        $request = new \WP_REST_Request();
        $request->set_param('settings', array_merge(Helper::getAuthSettings(), $overrides));

        $result = SettingsController::updateSettings($request);

        Helper::resetStatics();

        return $result;
    }

    /* ---------------------------------------------------------------------
     * Saving the policy
     * ------------------------------------------------------------------ */

    public function testThePolicyIsSaved()
    {
        $result = $this->save([
            'totp_2fa'            => 'yes',
            'totp_2fa_roles'      => ['administrator', 'editor'],
            'totp_required_roles' => ['administrator']
        ]);

        $this->assertNotWPError($result);
        $this->assertSame(['administrator', 'editor'], Helper::getSetting('totp_2fa_roles'));
        $this->assertSame(['administrator'], Helper::getSetting('totp_required_roles'));
    }

    /**
     * The screen narrows the choices so this cannot normally be built, but the API is
     * reachable on its own and must not accept a policy that locks a role out.
     */
    public function testARoleCannotBeRequiredWithoutBeingAllowed()
    {
        $result = $this->save([
            'totp_2fa'            => 'yes',
            'totp_2fa_roles'      => ['editor'],
            'totp_required_roles' => ['administrator']
        ]);

        $this->assertWPError($result);
        $this->assertArrayHasKey('totp_required_roles', $result->get_error_data());
    }

    public function testAnEmptyAllowListAcceptsAnyRequiredRole()
    {
        $result = $this->save([
            'totp_2fa'            => 'yes',
            'totp_2fa_roles'      => [],
            'totp_required_roles' => ['administrator']
        ]);

        $this->assertNotWPError($result);
    }

    public function testNobodyCanBeRequiredWhileTheMethodIsOff()
    {
        $result = $this->save([
            'totp_2fa'            => 'no',
            'totp_required_roles' => ['administrator']
        ]);

        $this->assertWPError($result);
    }

    public function testEmailCodesStillNeedARole()
    {
        $result = $this->save([
            'email2fa'       => 'yes',
            'email2fa_roles' => []
        ]);

        $this->assertWPError($result);
        $this->assertArrayHasKey('email2fa_roles', $result->get_error_data());
    }

    /**
     * Saving writes the payload over the whole option, so a key the payload leaves out
     * falls back to its default rather than keeping what was stored. That is the trap
     * the "apply recommended settings" button used to fall into: it sent a fresh object
     * that happened not to mention the digest schedule, silently resetting it.
     *
     * Pinned here because the fix lives in the screen that builds the payload, which
     * means nothing on this side would notice it being reintroduced.
     */
    public function testAKeyMissingFromThePayloadFallsBackToItsDefault()
    {
        $this->save(['digest_summary' => 'monthly']);
        $this->assertSame('monthly', Helper::getSetting('digest_summary'));

        $settings = Helper::getAuthSettings();
        unset($settings['digest_summary']);

        $request = new \WP_REST_Request();
        $request->set_param('settings', $settings);

        $this->assertNotWPError(SettingsController::updateSettings($request));
        Helper::resetStatics();

        $this->assertSame('', Helper::getSetting('digest_summary'));
    }

    /* ---------------------------------------------------------------------
     * The enrollment panel
     * ------------------------------------------------------------------ */

    public function testItReportsWhoIsEnrolled()
    {
        TotpTwoFaMethod::activate($this->admin, TotpProvider::generateSecret());
        TotpTwoFaMethod::generateRecoveryCodes($this->admin);

        $request = new \WP_REST_Request();
        $result = TwoFaController::getUsers($request);

        $this->assertSame(1, $result['summary']['enrolled']);

        $row = null;
        foreach ($result['users']['data'] as $user) {
            if ($user['id'] === $this->admin->ID) {
                $row = $user;
            }
        }

        $this->assertNotNull($row);
        $this->assertTrue($row['totp_enrolled']);
        $this->assertSame(TotpTwoFaMethod::RECOVERY_CODE_COUNT, $row['recovery_codes']);
        $this->assertTrue($row['can_edit']);
    }

    /**
     * Enrollment lives in user meta, so this has to be a query rather than a filter over
     * whichever users happen to land on the first page.
     */
    public function testTheEnrolledFilterQueriesRatherThanFiltersThePage()
    {
        $other = $this->factory->user->create_and_get(['role' => 'subscriber']);
        TotpTwoFaMethod::activate($other, TotpProvider::generateSecret());

        $request = new \WP_REST_Request();
        $request->set_param('filter', 'enrolled');

        $result = TwoFaController::getUsers($request);

        $ids = array_column($result['users']['data'], 'id');

        $this->assertSame([$other->ID], $ids);
        $this->assertSame(1, $result['users']['total']);
    }

    public function testTheNotEnrolledFilterExcludesThem()
    {
        TotpTwoFaMethod::activate($this->admin, TotpProvider::generateSecret());

        $request = new \WP_REST_Request();
        $request->set_param('filter', 'not_enrolled');

        $result = TwoFaController::getUsers($request);

        $this->assertNotContains($this->admin->ID, array_column($result['users']['data'], 'id'));
    }

    public function testAnAdminCanTurnOffALostDevice()
    {
        $target = $this->factory->user->create_and_get(['role' => 'subscriber']);
        TotpTwoFaMethod::activate($target, TotpProvider::generateSecret());

        $request = new \WP_REST_Request();
        $request->set_param('id', $target->ID);

        $result = TwoFaController::resetUser($request);

        $this->assertNotWPError($result);
        $this->assertFalse(TotpTwoFaMethod::isEnrolled($target->ID));
        $this->assertSame(0, $result['summary']['enrolled']);
    }

    /**
     * The endpoint's own permission is manage_options, but turning a second factor off
     * is the one action here that weakens an account, so it re-checks against that
     * specific user rather than trusting the door it came through.
     */
    public function testSomeoneWhoCannotEditTheUserCannotResetThem()
    {
        $target = $this->factory->user->create_and_get(['role' => 'administrator']);
        TotpTwoFaMethod::activate($target, TotpProvider::generateSecret());

        wp_set_current_user($this->factory->user->create(['role' => 'subscriber']));

        $request = new \WP_REST_Request();
        $request->set_param('id', $target->ID);

        $result = TwoFaController::resetUser($request);

        $this->assertWPError($result);
        $this->assertTrue(TotpTwoFaMethod::isEnrolled($target->ID));
    }

    public function testResettingSomeoneWithNothingSetUpIsRefused()
    {
        $target = $this->factory->user->create_and_get(['role' => 'subscriber']);

        $request = new \WP_REST_Request();
        $request->set_param('id', $target->ID);

        $this->assertWPError(TwoFaController::resetUser($request));
    }

    public function testAMissingUserIsRefused()
    {
        $request = new \WP_REST_Request();
        $request->set_param('id', 99999999);

        $this->assertWPError(TwoFaController::resetUser($request));
    }
}
