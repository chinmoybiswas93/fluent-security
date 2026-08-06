<?php

namespace FluentAuth\Tests\Unit;

use FluentAuth\App\Helpers\Helper;
use FluentAuth\App\Hooks\Handlers\LoginSecurityHandler;
use FluentAuth\App\Hooks\Handlers\TwoFaHandler;

/**
 * The emailed 2FA code is only ~800k possible values, so the number of guesses
 * allowed against it is the whole of its strength. These tests pin that bound down.
 */
class TwoFaHandlerTest extends BaseTestCase
{
    private $handler;

    private $user;

    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}fls_auth_logs");
        $wpdb->query("DELETE FROM {$wpdb->prefix}fls_login_hashes");

        $settings = Helper::getAuthSettings();
        $settings['email2fa'] = 'yes';
        $settings['email2fa_roles'] = ['administrator'];
        $settings['login_try_limit'] = 5;
        $settings['login_try_timing'] = 30;
        update_option('__fls_auth_settings', $settings);
        Helper::resetStatics();

        $this->handler = new TwoFaHandler();
        $this->user = $this->factory->user->create_and_get(['role' => 'administrator']);

        unset($_REQUEST['login_passcode'], $_REQUEST['login_hash']);
    }

    public function tearDown(): void
    {
        unset($_REQUEST['login_passcode'], $_REQUEST['login_hash']);
        parent::tearDown();
    }

    public function throwingDieHandler()
    {
        return function ($message = '') {
            throw new \WPDieException((string)$message);
        };
    }

    /**
     * verify2FaEmailCode() terminates through wp_send_json(); capture what it emitted.
     */
    private function verify($code, $hash)
    {
        $_REQUEST['login_passcode'] = $code;
        $_REQUEST['login_hash'] = $hash;

        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', [$this, 'throwingDieHandler']);

        ob_start();
        try {
            $this->handler->verify2FaEmailCode();
        } catch (\WPDieException $e) {
            // expected: wp_send_json() ends the request
        }
        $output = ob_get_clean();

        remove_filter('wp_doing_ajax', '__return_true');
        remove_filter('wp_die_ajax_handler', [$this, 'throwingDieHandler']);

        return json_decode($output, true);
    }

    /**
     * Issues a real code through the normal path and captures its plaintext value.
     */
    private function issueCode()
    {
        $captured = null;

        $spy = function ($data) use (&$captured) {
            $captured = $data;
        };

        add_action('fls_send_2fa_code', $spy, 10, 1);
        $return = $this->handler->sendAndGet2FaConfirmFormUrl($this->user, 'both');
        remove_action('fls_send_2fa_code', $spy, 10);

        return [
            'code' => $captured['two_fa_code'],
            'hash' => $return['login_hash'],
        ];
    }

    private function hashRow($hash)
    {
        return flsDb()->table('fls_login_hashes')->where('login_hash', $hash)->first();
    }

    public function testACorrectCodeIsAccepted()
    {
        $issued = $this->issueCode();

        $response = $this->verify($issued['code'], $issued['hash']);

        $this->assertArrayHasKey('redirect', $response);
        $this->assertSame('used', $this->hashRow($issued['hash'])->status);
    }

    public function testAWrongCodeIsRejectedAndCounted()
    {
        $issued = $this->issueCode();

        $response = $this->verify('000000', $issued['hash']);

        $this->assertArrayNotHasKey('redirect', $response);
        $this->assertSame(1, (int)$this->hashRow($issued['hash'])->used_count);
        $this->assertSame('issued', $this->hashRow($issued['hash'])->status);
    }

    /**
     * The whole point: the attempt cap used to sit after the code comparison, so it
     * only ever applied to a code that had already matched. A wrong code could be
     * retried without limit until it hit.
     */
    public function testCodeIsBurnedAfterMaxAttemptsAndTheRealCodeNoLongerWorks()
    {
        $issued = $this->issueCode();

        for ($i = 0; $i < TwoFaHandler::MAX_VERIFY_ATTEMPTS; $i++) {
            $response = $this->verify('000000', $issued['hash']);
            $this->assertArrayNotHasKey('redirect', $response);
        }

        $row = $this->hashRow($issued['hash']);
        $this->assertSame('failed', $row->status, 'The code must be burned');
        $this->assertSame(TwoFaHandler::MAX_VERIFY_ATTEMPTS, (int)$row->used_count);

        // Even the genuine code is dead now.
        $response = $this->verify($issued['code'], $issued['hash']);
        $this->assertArrayNotHasKey('redirect', $response);
    }

    public function testFurtherGuessesAfterTheCapDoNotKeepIncrementing()
    {
        $issued = $this->issueCode();

        for ($i = 0; $i < TwoFaHandler::MAX_VERIFY_ATTEMPTS + 4; $i++) {
            $this->verify('000000', $issued['hash']);
        }

        $this->assertSame(
            TwoFaHandler::MAX_VERIFY_ATTEMPTS,
            (int)$this->hashRow($issued['hash'])->used_count
        );
    }

    /**
     * The first factor already passed to reach 2FA, so nothing was recorded as a
     * failure. Without this the IP limit never sees the guessing at all.
     */
    public function testAFailedGuessIsRecordedForTheIpAttemptLimit()
    {
        global $wpdb;

        // A fresh logger: the plugin's own instance may have already logged this request.
        $logger = new LoginSecurityHandler();
        add_action('wp_login_failed', [$logger, 'logFailedAuth'], 10, 2);

        $issued = $this->issueCode();
        $this->verify('000000', $issued['hash']);

        remove_action('wp_login_failed', [$logger, 'logFailedAuth'], 10);

        $count = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}fls_auth_logs
             WHERE `status` = 'failed' AND `media` = 'two_factor_email'"
        );

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testAnExpiredCodeIsRejectedWithoutBeingCompared()
    {
        $issued = $this->issueCode();

        flsDb()->table('fls_login_hashes')
            ->where('login_hash', $issued['hash'])
            ->update(['created_at' => date('Y-m-d H:i:s', current_time('timestamp') - 11 * 60)]);

        $response = $this->verify($issued['code'], $issued['hash']);

        $this->assertArrayNotHasKey('redirect', $response);
        $this->assertSame(0, (int)$this->hashRow($issued['hash'])->used_count);
    }

    public function testAnAlreadyUsedCodeCannotBeReplayed()
    {
        $issued = $this->issueCode();

        flsDb()->table('fls_login_hashes')
            ->where('login_hash', $issued['hash'])
            ->update(['status' => 'used']);

        $response = $this->verify($issued['code'], $issued['hash']);

        $this->assertArrayNotHasKey('redirect', $response);
    }

    /**
     * Someone holding the password can trigger the 2FA mail over and over. Throttle the
     * mail, but never the enforcement - returning nothing here would let the login
     * through without a second factor at all.
     */
    public function testCodeEmailsAreThrottledWhileTwoFactorStaysEnforced()
    {
        $sent = 0;
        $counter = function ($args) use (&$sent) {
            $sent++;
            return $args;
        };
        add_filter('wp_mail', $counter);

        $returns = [];
        for ($i = 0; $i < 9; $i++) {
            $returns[] = $this->handler->sendAndGet2FaConfirmFormUrl($this->user, 'both');
        }

        remove_filter('wp_mail', $counter);

        $this->assertSame(5, $sent, 'Emails stop once the request limit is passed');

        foreach ($returns as $i => $return) {
            $this->assertNotFalse($return, "Request {$i} must still enforce 2FA");
            $this->assertNotEmpty($return['login_hash']);
            $this->assertNotEmpty($return['redirect_to']);
        }
    }

    private function disableEmail2Fa()
    {
        $settings = Helper::getAuthSettings();
        $settings['email2fa'] = 'no';
        update_option('__fls_auth_settings', $settings);
        Helper::resetStatics();
    }

    /**
     * The account challenge has to work on sites that never turned email 2FA on -
     * that is the whole point of escalating instead of denying.
     */
    public function testAChallengeIssuesACodeEvenWhenEmail2FaIsOff()
    {
        $this->disableEmail2Fa();

        $this->assertFalse(
            $this->handler->sendAndGet2FaConfirmFormUrl($this->user, 'both'),
            'No 2FA without a challenge when the feature is off'
        );

        add_filter('fluent_auth/2fa_challenge_required', '__return_true');
        $handler = new TwoFaHandler();
        $issued = null;
        $spy = function ($data) use (&$issued) {
            $issued = $data;
        };
        add_action('fls_send_2fa_code', $spy, 10, 1);
        $return = $handler->sendAndGet2FaConfirmFormUrl($this->user, 'both');
        remove_action('fls_send_2fa_code', $spy, 10);
        remove_filter('fluent_auth/2fa_challenge_required', '__return_true');

        $this->assertNotFalse($return);
        $this->assertNotEmpty($return['login_hash']);

        $row = $this->hashRow($return['login_hash']);
        $this->assertSame(TwoFaHandler::CHALLENGE_USE_TYPE, $row->use_type);
        $this->assertNotEmpty($issued['two_fa_code']);
    }

    /**
     * A challenge code must stay redeemable after the attack subsides, otherwise the
     * owner is stranded holding a code the plugin no longer recognises.
     */
    public function testAChallengeCodeStillVerifiesOnceTheAttackSubsides()
    {
        $this->disableEmail2Fa();

        add_filter('fluent_auth/2fa_challenge_required', '__return_true');
        $handler = new TwoFaHandler();
        $issued = null;
        $spy = function ($data) use (&$issued) {
            $issued = $data;
        };
        add_action('fls_send_2fa_code', $spy, 10, 1);
        $return = $handler->sendAndGet2FaConfirmFormUrl($this->user, 'both');
        remove_action('fls_send_2fa_code', $spy, 10);
        remove_filter('fluent_auth/2fa_challenge_required', '__return_true');

        // Challenge no longer required, email 2FA still off: the code must work anyway.
        $response = $this->verify($issued['two_fa_code'], $return['login_hash']);

        $this->assertArrayHasKey('redirect', $response);
        $this->assertSame('used', $this->hashRow($return['login_hash'])->status);
    }

    public function testAWrongChallengeCodeIsStillCappedAndBurned()
    {
        $this->disableEmail2Fa();

        add_filter('fluent_auth/2fa_challenge_required', '__return_true');
        $handler = new TwoFaHandler();
        $issued = null;
        $spy = function ($data) use (&$issued) {
            $issued = $data;
        };
        add_action('fls_send_2fa_code', $spy, 10, 1);
        $return = $handler->sendAndGet2FaConfirmFormUrl($this->user, 'both');
        remove_action('fls_send_2fa_code', $spy, 10);
        remove_filter('fluent_auth/2fa_challenge_required', '__return_true');

        for ($i = 0; $i < TwoFaHandler::MAX_VERIFY_ATTEMPTS; $i++) {
            $this->verify('000000', $return['login_hash']);
        }

        $this->assertSame('failed', $this->hashRow($return['login_hash'])->status);
        $this->assertArrayNotHasKey(
            'redirect',
            $this->verify($issued['two_fa_code'], $return['login_hash'])
        );
    }

    /**
     * The magic link throttle counts rows in fls_login_hashes, which is shared with 2FA
     * and signup verification. Unscoped, an ordinary 2FA login ate into the user's
     * magic link allowance - and challenge codes would now land in that bucket too.
     */
    public function testTwoFactorCodesDoNotConsumeTheMagicLinkAllowance()
    {
        // Well past the limit of 5, but none of these are magic links.
        for ($i = 0; $i < 9; $i++) {
            $this->handler->sendAndGet2FaConfirmFormUrl($this->user, 'both');
        }

        $total = flsDb()->table('fls_login_hashes')
            ->where('ip_address', Helper::getIp())
            ->count();

        $magicOnly = flsDb()->table('fls_login_hashes')
            ->where('ip_address', Helper::getIp())
            ->where('use_type', 'magic_login')
            ->count();

        $this->assertSame(9, (int)$total, 'The unscoped count is what used to be used');
        $this->assertSame(0, (int)$magicOnly, 'Scoped, none of them touch the magic link quota');
    }

    public function testThrottledCodeStillHasToBeEnteredCorrectly()
    {
        for ($i = 0; $i < 6; $i++) {
            $this->handler->sendAndGet2FaConfirmFormUrl($this->user, 'both');
        }

        // The 7th is past the email throttle but must still be a working, required code.
        $issued = $this->issueCode();

        $this->assertArrayNotHasKey('redirect', $this->verify('000000', $issued['hash']));
        $this->assertArrayHasKey('redirect', $this->verify($issued['code'], $issued['hash']));
    }
}
