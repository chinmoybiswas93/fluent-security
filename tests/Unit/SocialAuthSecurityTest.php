<?php

namespace FluentAuth\Tests\Unit;

use FluentAuth\App\Helpers\Helper;
use FluentAuth\App\Services\AuthService;
use FluentAuth\App\Services\GoogleAuthService;

/**
 * Social login hands us an email address and we sign in whichever WordPress account
 * holds it, so the guarantees around that address are the whole security boundary.
 */
class SocialAuthSecurityTest extends BaseTestCase
{
    private $cookieBackup;

    public function setUp(): void
    {
        parent::setUp();

        $this->cookieBackup = $_COOKIE;
        unset($_COOKIE['fs_auth_state']);

        update_option('__fls_social_auth_settings', [
            // Anything but 'wp_config', or the keys get read from constants instead.
            'google_key_method'    => 'db',
            'google_client_id'     => '1234567890-test.apps.googleusercontent.com',
            'google_client_secret' => 'secret',
            'enable_google'        => 'yes',
        ]);
        Helper::resetStatics();
    }

    public function tearDown(): void
    {
        $_COOKIE = $this->cookieBackup;
        parent::tearDown();
    }

    // ------------------------------------------------------------- state token

    public function testAStateTokenValidatesAgainstItself()
    {
        $state = AuthService::setStateToken();

        $this->assertNotEmpty($state);
        $this->assertTrue(AuthService::verifyStateToken($state));
    }

    public function testAWrongOrMissingStateIsRejected()
    {
        AuthService::setStateToken();

        $this->assertFalse(AuthService::verifyStateToken('not-the-state'));
        $this->assertFalse(AuthService::verifyStateToken(''));
        $this->assertFalse(AuthService::verifyStateToken(null));
    }

    public function testStateIsRejectedWhenNoFlowWasStarted()
    {
        unset($_COOKIE['fs_auth_state']);

        $this->assertFalse(AuthService::verifyStateToken('anything'));
    }

    /**
     * A state token covers exactly one callback. Leaving it valid kept the whole
     * callback replayable for as long as the cookie lived.
     */
    public function testStateCannotBeReusedOnceSpent()
    {
        $state = AuthService::setStateToken();
        $this->assertTrue(AuthService::verifyStateToken($state));

        AuthService::clearStateToken();

        $this->assertFalse(AuthService::verifyStateToken($state));
    }

    public function testStateTokensAreUnpredictableAndDistinct()
    {
        $seen = [];

        for ($i = 0; $i < 20; $i++) {
            $state = AuthService::setStateToken();
            $this->assertGreaterThanOrEqual(32, strlen($state));
            $seen[$state] = true;
        }

        $this->assertCount(20, $seen);
    }

    // -------------------------------------------------- google email_verified

    /**
     * Without this, anyone who can attach an address to a Google account could sign in
     * as whichever WordPress user holds that address - an administrator included.
     */
    public function testAGoogleTokenWithAnUnverifiedEmailIsRejected()
    {
        $payload = [
            'aud'            => '1234567890-test.apps.googleusercontent.com',
            'email'          => 'victim@example.test',
            'email_verified' => 'false',
        ];

        $this->assertWpErrorWithCode($this->verifyPayload($payload), 'email_unverified');
    }

    public function testAGoogleTokenWithNoVerifiedClaimAtAllIsRejected()
    {
        $payload = [
            'aud'   => '1234567890-test.apps.googleusercontent.com',
            'email' => 'victim@example.test',
        ];

        $this->assertWpErrorWithCode($this->verifyPayload($payload), 'email_unverified');
    }

    public function testAVerifiedGoogleTokenIsAccepted()
    {
        foreach (['true', true] as $verified) {
            $payload = [
                'aud'            => '1234567890-test.apps.googleusercontent.com',
                'email'          => 'someone@example.test',
                'email_verified' => $verified,
            ];

            $result = $this->verifyPayload($payload);

            $this->assertIsArray($result);
            $this->assertSame('someone@example.test', $result['email']);
        }
    }

    /**
     * A site that has not filled in its client id must not accept anything. The empty
     * configured value and a missing `aud` used to compare equal.
     */
    public function testAnUnconfiguredClientIdAcceptsNothing()
    {
        update_option('__fls_social_auth_settings', [
            'google_key_method' => 'db',
            'google_client_id'  => '',
            'enable_google'     => 'yes',
        ]);
        Helper::resetStatics();

        $payload = ['email' => 'victim@example.test', 'email_verified' => 'true'];

        $this->assertWpErrorWithCode($this->verifyPayload($payload), 'token_error');
    }

    public function testATokenInfoErrorResponseIsRejected()
    {
        $payload = ['error_description' => 'Invalid Value'];

        $this->assertWpErrorWithCode($this->verifyPayload($payload), 'token_error');
    }

    public function testATokenIssuedToADifferentGoogleAppIsRejected()
    {
        $payload = [
            'aud'            => 'somebody-elses-app.apps.googleusercontent.com',
            'email'          => 'someone@example.test',
            'email_verified' => 'true',
        ];

        $this->assertWpErrorWithCode($this->verifyPayload($payload), 'token_error');
    }

    /**
     * Drives verifyClientToken() with a stubbed tokeninfo response.
     */
    private function verifyPayload($payload)
    {
        $stub = function () use ($payload) {
            return [
                'headers'  => [],
                'body'     => wp_json_encode($payload),
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies'  => [],
                'filename' => null,
            ];
        };

        add_filter('pre_http_request', $stub, 10, 3);
        $result = GoogleAuthService::verifyClientToken('stub.token.value');
        remove_filter('pre_http_request', $stub, 10);

        return $result;
    }

    // ---------------------------------------------------------- open redirect

    public function testOffSiteRedirectTargetsAreRefused()
    {
        $this->assertSame(
            admin_url(),
            Helper::getValidatedRedirectUrl('https://evil.example.com/phish', admin_url())
        );
    }

    public function testOnSiteRedirectTargetsSurvive()
    {
        $onSite = home_url('/welcome');

        $this->assertSame($onSite, Helper::getValidatedRedirectUrl($onSite, admin_url()));
    }
}
