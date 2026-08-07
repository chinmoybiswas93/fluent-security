<?php

namespace FluentAuth\Tests\Unit;

use FluentAuth\App\Helpers\Helper;
use FluentAuth\App\Hooks\Handlers\TotpEnforcementHandler;
use FluentAuth\App\Services\TwoFa\TotpProvider;
use FluentAuth\App\Services\TwoFa\TotpTwoFaMethod;

/**
 * The rules deciding who may set up an authenticator app and who has to.
 *
 * Both directions can hurt: too loose and the policy is decorative, too tight and it
 * shuts an administrator out of their own site. The cases that matter most here are
 * the ones where a setting is changed after people have already enrolled.
 */
class TotpPolicyTest extends BaseTestCase
{
    private $admin;

    private $subscriber;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->factory->user->create_and_get(['role' => 'administrator']);
        $this->subscriber = $this->factory->user->create_and_get(['role' => 'subscriber']);

        $this->policy('yes', [], []);
    }

    public function tearDown(): void
    {
        remove_all_filters('fluent_auth/totp_enabled');
        wp_set_current_user(0);
        parent::tearDown();
    }

    private function policy($enabled, $allowed, $required)
    {
        $settings = Helper::getAuthSettings();
        $settings['totp_2fa'] = $enabled;
        $settings['totp_2fa_roles'] = $allowed;
        $settings['totp_required_roles'] = $required;
        update_option('__fls_auth_settings', $settings);
        Helper::resetStatics();
    }

    public function testItIsOffUntilSwitchedOn()
    {
        $this->policy('no', [], []);

        $this->assertFalse(TotpTwoFaMethod::isAllowedForUser($this->admin));
    }

    public function testAnEmptyAllowListMeansEveryRoleRatherThanNobody()
    {
        $this->assertTrue(TotpTwoFaMethod::isAllowedForUser($this->admin));
        $this->assertTrue(TotpTwoFaMethod::isAllowedForUser($this->subscriber));
    }

    public function testNamingRolesRestrictsItToThem()
    {
        $this->policy('yes', ['administrator'], []);

        $this->assertTrue(TotpTwoFaMethod::isAllowedForUser($this->admin));
        $this->assertFalse(TotpTwoFaMethod::isAllowedForUser($this->subscriber));
    }

    public function testNobodyIsRequiredByDefault()
    {
        $this->assertFalse(TotpTwoFaMethod::isRequiredForUser($this->admin));
    }

    public function testARequiredRoleIsRequired()
    {
        $this->policy('yes', [], ['administrator']);

        $this->assertTrue(TotpTwoFaMethod::isRequiredForUser($this->admin));
        $this->assertFalse(TotpTwoFaMethod::isRequiredForUser($this->subscriber));
    }

    /**
     * The settings screen will not save this combination, but a filter or a direct
     * option write can still produce it - and demanding something the profile screen
     * refuses to offer is a locked out user, not a secured one.
     */
    public function testARoleCannotBeRequiredWithoutBeingAllowed()
    {
        $this->policy('yes', ['editor'], ['administrator']);

        $this->assertFalse(
            TotpTwoFaMethod::isRequiredForUser($this->admin),
            'A role that cannot set one up must never be told it has to.'
        );
    }

    public function testTurningTheMethodOffCancelsTheRequirement()
    {
        $this->policy('no', [], ['administrator']);

        $this->assertFalse(TotpTwoFaMethod::isRequiredForUser($this->admin));
    }

    /**
     * Someone who enrolled while their role was allowed keeps a working secret, but the
     * method stops being asked for once the policy no longer covers them - otherwise
     * turning it off for a role would leave those users facing a factor the site says
     * they should not have.
     */
    public function testRemovingARoleStopsTheMethodBeingUsedByItsMembers()
    {
        TotpTwoFaMethod::activate($this->admin, TotpProvider::generateSecret());

        $method = new TotpTwoFaMethod();
        $this->assertTrue($method->isAvailableForUser($this->admin));

        $this->policy('yes', ['editor'], []);

        $this->assertFalse($method->isAvailableForUser($this->admin));
        $this->assertTrue(TotpTwoFaMethod::isEnrolled($this->admin), 'The secret itself is left alone.');
    }

    public function testTheFilterStillOverridesEverything()
    {
        add_filter('fluent_auth/totp_enabled', '__return_false');

        $this->assertFalse(TotpTwoFaMethod::isAllowedForUser($this->admin));
    }

    /* ---------------------------------------------------------------------
     * Enforcement
     * ------------------------------------------------------------------ */

    public function testAnUnenrolledRequiredUserIsGated()
    {
        $this->policy('yes', [], ['administrator']);
        wp_set_current_user($this->admin->ID);

        $this->assertTrue((new TotpEnforcementHandler())->needsEnrollment());
    }

    public function testEnrollingClearsTheGate()
    {
        $this->policy('yes', [], ['administrator']);
        wp_set_current_user($this->admin->ID);

        TotpTwoFaMethod::activate($this->admin, TotpProvider::generateSecret());

        $this->assertFalse((new TotpEnforcementHandler())->needsEnrollment());
    }

    public function testAUserWhoseRoleIsNotRequiredIsNeverGated()
    {
        $this->policy('yes', [], ['administrator']);
        wp_set_current_user($this->subscriber->ID);

        $this->assertFalse((new TotpEnforcementHandler())->needsEnrollment());
    }

    public function testLoggedOutRequestsAreNeverGated()
    {
        $this->policy('yes', [], ['administrator']);
        wp_set_current_user(0);

        $this->assertFalse((new TotpEnforcementHandler())->needsEnrollment());
    }

    /**
     * A redirect sent in reply to an ajax call breaks the caller instead of reaching
     * anybody, so the gate has to stay out of the way of them.
     */
    public function testAjaxRequestsAreNotRedirected()
    {
        $this->policy('yes', [], ['administrator']);
        wp_set_current_user($this->admin->ID);

        add_filter('wp_doing_ajax', '__return_true');

        $this->assertFalse((new TotpEnforcementHandler())->needsEnrollment());

        remove_filter('wp_doing_ajax', '__return_true');
    }
}
