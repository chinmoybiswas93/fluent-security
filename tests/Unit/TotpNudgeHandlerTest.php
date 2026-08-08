<?php

namespace FluentAuth\Tests\Unit;

use FluentAuth\App\Helpers\Helper;
use FluentAuth\App\Hooks\Handlers\TotpNudgeHandler;
use FluentAuth\App\Services\TwoFa\TotpProvider;
use FluentAuth\App\Services\TwoFa\TotpTwoFaMethod;

/**
 * The offer made after signing in.
 *
 * It interrupts a login, so what matters is who it stops and who it lets past. Every
 * test here is about the second group.
 */
class TotpNudgeHandlerTest extends BaseTestCase
{
    private $handler;

    private $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->policy(['subscriber'], []);

        $this->handler = new TotpNudgeHandler();
        $this->user = $this->factory->user->create_and_get(['role' => 'subscriber']);
    }

    public function tearDown(): void
    {
        remove_all_filters('fluent_auth/ask_to_set_up_totp');
        wp_set_current_user(0);
        parent::tearDown();
    }

    private function policy($allowed, $required)
    {
        $settings = Helper::getAuthSettings();
        $settings['totp_2fa'] = 'yes';
        $settings['totp_2fa_roles'] = $allowed;
        $settings['totp_required_roles'] = $required;
        update_option('__fls_auth_settings', $settings);
        Helper::resetStatics();
    }

    public function testItAsksSomebodyWhoCouldHaveOneAndDoesNot()
    {
        $this->assertTrue($this->handler->shouldAsk($this->user));
    }

    public function testItDoesNotAskSomebodyWhoAlreadyHasOne()
    {
        TotpTwoFaMethod::activate($this->user, TotpProvider::generateSecret());

        $this->assertFalse($this->handler->shouldAsk($this->user));
    }

    public function testItDoesNotAskARoleThatIsNotOfferedOne()
    {
        $this->policy(['administrator'], []);

        $this->assertFalse($this->handler->shouldAsk($this->user));
    }

    public function testItDoesNotAskWhileTheMethodIsOff()
    {
        $settings = Helper::getAuthSettings();
        $settings['totp_2fa'] = 'no';
        update_option('__fls_auth_settings', $settings);
        Helper::resetStatics();

        $this->assertFalse($this->handler->shouldAsk($this->user));
    }

    /**
     * The enforcement gate sends the same people to the same screen with no way past it.
     * Offering "not now" first would only teach them that there is one.
     */
    public function testItStepsAsideForAnybodyTheGateCovers()
    {
        $this->policy(['subscriber'], ['subscriber']);

        $this->assertFalse($this->handler->shouldAsk($this->user));
    }

    public function testTheFilterCanStopItAsking()
    {
        add_filter('fluent_auth/ask_to_set_up_totp', '__return_false');

        $this->assertFalse($this->handler->shouldAsk($this->user));
    }

    /* ---------------------------------------------------------------------
     * Once per login
     * ------------------------------------------------------------------ */

    public function testALoginLeavesTheQuestionWaiting()
    {
        $this->handler->markPending($this->user->user_login, $this->user);

        $this->assertSame('yes', get_user_meta($this->user->ID, TotpNudgeHandler::PENDING_META, true));
    }

    public function testALoginBySomebodyNotWorthAskingLeavesNothingBehind()
    {
        TotpTwoFaMethod::activate($this->user, TotpProvider::generateSecret());

        $this->handler->markPending($this->user->user_login, $this->user);

        $this->assertSame('', get_user_meta($this->user->ID, TotpNudgeHandler::PENDING_META, true));
    }

    /**
     * Asked once per login, not once per page. The flag is spent whatever the answer
     * turns out to be, so browsing on after "not now" is not asked again.
     */
    public function testAskingSpendsTheQuestionForThatLogin()
    {
        wp_set_current_user($this->user->ID);
        $this->handler->markPending($this->user->user_login, $this->user);

        $asked = $this->captureRedirect(function () {
            $this->handler->maybeAsk();
        });

        $this->assertNotNull($asked);
        $this->assertStringContainsString('action=fls_2fa_setup', $asked);
        $this->assertStringContainsString('fls_offered=1', $asked);

        $this->assertNull(
            $this->captureRedirect(function () {
                $this->handler->maybeAsk();
            }),
            'The next page load in the same session must not ask again.'
        );
    }

    public function testItAsksNothingOfAnAjaxRequest()
    {
        wp_set_current_user($this->user->ID);
        $this->handler->markPending($this->user->user_login, $this->user);

        add_filter('wp_doing_ajax', '__return_true');

        $asked = $this->captureRedirect(function () {
            $this->handler->maybeAsk();
        });

        remove_filter('wp_doing_ajax', '__return_true');

        $this->assertNull($asked, 'A redirect here breaks the caller instead of reaching anybody.');
        $this->assertSame(
            'yes',
            get_user_meta($this->user->ID, TotpNudgeHandler::PENDING_META, true),
            'And it must not spend the question either - the next real page load still owes it.'
        );
    }

    /**
     * maybeAsk() ends in exit(), so the redirect is intercepted at the filter and
     * unwound before it gets there.
     *
     * @param $callback callable
     * @return string|null
     */
    private function captureRedirect($callback)
    {
        $captured = null;

        $catch = function ($location) use (&$captured) {
            $captured = $location;
            throw new \RuntimeException('redirected');
        };

        add_filter('wp_redirect', $catch);

        try {
            $callback();
        } catch (\RuntimeException $e) {
            // Expected: this is how the exit() below the redirect is escaped.
        } finally {
            remove_filter('wp_redirect', $catch);
        }

        return $captured;
    }
}
