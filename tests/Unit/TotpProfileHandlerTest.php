<?php

namespace FluentAuth\Tests\Unit;

use FluentAuth\App\Hooks\Handlers\TotpProfileHandler;
use FluentAuth\App\Services\TwoFa\TotpProvider;
use FluentAuth\App\Services\TwoFa\TotpTwoFaMethod;

/**
 * Enrollment decides who can attach a second factor to an account, so the interesting
 * cases are the ones where somebody tries to do it to somebody else.
 */
class TotpProfileHandlerTest extends BaseTestCase
{
    private $handler;

    private $user;

    public function setUp(): void
    {
        parent::setUp();

        $settings = \FluentAuth\App\Helpers\Helper::getAuthSettings();
        $settings['totp_2fa'] = 'yes';
        $settings['totp_2fa_roles'] = [];
        $settings['totp_required_roles'] = [];
        update_option('__fls_auth_settings', $settings);
        \FluentAuth\App\Helpers\Helper::resetStatics();

        $this->handler = new TotpProfileHandler();
        $this->user = $this->factory->user->create_and_get(['role' => 'administrator']);

        $_POST = [];
    }

    public function tearDown(): void
    {
        $_POST = [];
        wp_set_current_user(0);
        parent::tearDown();
    }

    private function actAs($user)
    {
        wp_set_current_user($user->ID);
        $_POST['_fls_totp_nonce'] = wp_create_nonce(TotpProfileHandler::NONCE_ACTION);
    }

    private function codeFor($secret)
    {
        return TotpProvider::getCodeForCounter($secret, (int)floor(time() / TotpProvider::PERIOD));
    }

    public function testConfirmingTheCodeActivatesTheApp()
    {
        $this->actAs($this->user);

        $pending = TotpTwoFaMethod::getOrCreatePendingSecret($this->user);
        $_POST['fls_totp_confirm_code'] = $this->codeFor($pending);

        $this->handler->handleUpdate($this->user->ID);

        $this->assertTrue(TotpTwoFaMethod::isEnrolled($this->user->ID));
        $this->assertSame(
            TotpTwoFaMethod::RECOVERY_CODE_COUNT,
            TotpTwoFaMethod::getRemainingRecoveryCount($this->user->ID),
            'Activation has to hand back recovery codes, or a lost phone is a lost account.'
        );
    }

    /**
     * The code typed to confirm setup is a valid code for the current step, so without
     * spending that step it could be replayed straight at the login form.
     */
    public function testTheConfirmingCodeIsSpentByActivation()
    {
        $this->actAs($this->user);

        $pending = TotpTwoFaMethod::getOrCreatePendingSecret($this->user);
        $code = $this->codeFor($pending);
        $_POST['fls_totp_confirm_code'] = $code;

        $this->handler->handleUpdate($this->user->ID);

        $method = new TotpTwoFaMethod();

        $this->assertFalse($method->verifyProof($this->user, null, ['login_passcode' => $code]));
    }

    public function testAWrongCodeLeavesTheAccountUnenrolled()
    {
        $this->actAs($this->user);

        TotpTwoFaMethod::getOrCreatePendingSecret($this->user);
        $_POST['fls_totp_confirm_code'] = '000000';

        $this->handler->handleUpdate($this->user->ID);

        $this->assertFalse(TotpTwoFaMethod::isEnrolled($this->user->ID));
    }

    public function testWithoutAValidNonceNothingHappens()
    {
        wp_set_current_user($this->user->ID);

        $pending = TotpTwoFaMethod::getOrCreatePendingSecret($this->user);
        $_POST['fls_totp_confirm_code'] = $this->codeFor($pending);
        $_POST['_fls_totp_nonce'] = 'not-a-nonce';

        $this->handler->handleUpdate($this->user->ID);

        $this->assertFalse(TotpTwoFaMethod::isEnrolled($this->user->ID));
    }

    /**
     * Pairing an authenticator the account holder does not have would lock them out,
     * so it stays confined to their own profile even for an administrator.
     */
    public function testAnAdminCannotEnrollSomeoneElse()
    {
        $target = $this->factory->user->create_and_get(['role' => 'subscriber']);
        $admin = $this->factory->user->create_and_get(['role' => 'administrator']);

        $this->actAs($admin);

        $pending = TotpTwoFaMethod::getOrCreatePendingSecret($target);
        $_POST['fls_totp_confirm_code'] = $this->codeFor($pending);

        $this->handler->handleUpdate($target->ID);

        $this->assertFalse(TotpTwoFaMethod::isEnrolled($target->ID));
    }

    /**
     * The other direction is the recovery path: someone has lost their phone and needs
     * an administrator to take the factor off the account.
     */
    public function testAnAdminCanDisableSomeoneElse()
    {
        $target = $this->factory->user->create_and_get(['role' => 'subscriber']);
        $admin = $this->factory->user->create_and_get(['role' => 'administrator']);

        TotpTwoFaMethod::activate($target, TotpProvider::generateSecret());
        $this->assertTrue(TotpTwoFaMethod::isEnrolled($target->ID));

        $this->actAs($admin);
        $_POST['fls_totp_disable'] = 'yes';

        $this->handler->handleUpdate($target->ID);

        $this->assertFalse(TotpTwoFaMethod::isEnrolled($target->ID));
    }

    public function testASubscriberCannotDisableAnotherAccount()
    {
        $target = $this->factory->user->create_and_get(['role' => 'administrator']);
        $attacker = $this->factory->user->create_and_get(['role' => 'subscriber']);

        TotpTwoFaMethod::activate($target, TotpProvider::generateSecret());

        $this->actAs($attacker);
        $_POST['fls_totp_disable'] = 'yes';

        $this->handler->handleUpdate($target->ID);

        $this->assertTrue(
            TotpTwoFaMethod::isEnrolled($target->ID),
            'Turning off someone else\'s second factor requires the capability to edit them.'
        );
    }

    public function testRegeneratingReplacesTheRecoveryCodes()
    {
        $this->actAs($this->user);

        TotpTwoFaMethod::activate($this->user, TotpProvider::generateSecret());
        $old = TotpTwoFaMethod::generateRecoveryCodes($this->user);

        $_POST['fls_totp_regenerate_recovery'] = 'yes';

        $this->handler->handleUpdate($this->user->ID);

        $method = new TotpTwoFaMethod();

        $this->assertSame(TotpTwoFaMethod::RECOVERY_CODE_COUNT, TotpTwoFaMethod::getRemainingRecoveryCount($this->user->ID));
        $this->assertFalse($method->verifyProof($this->user, null, ['login_passcode' => $old[0]]));
    }

    /**
     * Re-submitting an old setup form after enrollment must not quietly repair a
     * secret the user has since replaced.
     */
    public function testAnAlreadyEnrolledAccountIgnoresAFreshConfirmation()
    {
        $this->actAs($this->user);

        $original = TotpProvider::generateSecret();
        TotpTwoFaMethod::activate($this->user, $original);

        $other = TotpTwoFaMethod::getOrCreatePendingSecret($this->user);
        $_POST['fls_totp_confirm_code'] = $this->codeFor($other);

        $this->handler->handleUpdate($this->user->ID);

        $this->assertSame($original, TotpTwoFaMethod::getSecret($this->user->ID));
    }
}
