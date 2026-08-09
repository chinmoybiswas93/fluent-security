<?php

namespace FluentAuth\Tests\Unit;

use FluentAuth\App\Helpers\Helper;
use FluentAuth\App\Http\Controllers\SocialAuthApiController;

/**
 * Saving social login settings clears the stored credentials when it is switched off.
 *
 * That is correct when switching it off is what was asked for, and destructive when it
 * is not - the client ID and secret are gone and cannot be recovered from the plugin.
 * So the line between "asked to turn this off" and "this request arrived malformed"
 * carries real weight, and these pin it.
 */
class SocialAuthSettingsSaveTest extends BaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        update_option('__fls_social_auth_settings', [
            'enabled'              => 'yes',
            'enable_google'        => 'yes',
            'google_one_tap'       => 'yes',
            'google_key_method'    => 'db',
            'google_client_id'     => '1234567890-test.apps.googleusercontent.com',
            'google_client_secret' => 'a-secret-worth-keeping',
        ]);
        Helper::resetStatics();
    }

    private function save($payload)
    {
        $request = new \WP_REST_Request();
        $request->set_param('settings', $payload);

        return SocialAuthApiController::saveSettings($request);
    }

    private function stored()
    {
        return get_option('__fls_social_auth_settings');
    }

    public function testAnEmptyPayloadIsRefusedRatherThanTreatedAsSwitchingItOff()
    {
        $result = $this->save(null);

        $this->assertWPError($result);

        $stored = $this->stored();
        $this->assertEquals('yes', $stored['enabled']);
        $this->assertEquals('a-secret-worth-keeping', $stored['google_client_secret']);
    }

    public function testAPayloadThatIsNotASettingsArrayIsRefused()
    {
        $this->assertWPError($this->save('nonsense'));
        $this->assertEquals('a-secret-worth-keeping', $this->stored()['google_client_secret']);

        // An array, but not one carrying the switch the decision is made on.
        $this->assertWPError($this->save(['google_client_id' => 'x']));
        $this->assertEquals('a-secret-worth-keeping', $this->stored()['google_client_secret']);
    }

    public function testActuallySwitchingItOffStillClearsTheCredentials()
    {
        $result = $this->save([
            'enabled'              => 'no',
            'enable_google'        => 'no',
            'google_client_id'     => '1234567890-test.apps.googleusercontent.com',
            'google_client_secret' => 'a-secret-worth-keeping',
        ]);

        $this->assertNotWPError($result);

        $stored = $this->stored();
        $this->assertEquals('no', $stored['enabled']);
        $this->assertEquals('', $stored['google_client_secret']);
    }

    public function testAValidPayloadIsSaved()
    {
        $result = $this->save([
            'enabled'              => 'yes',
            'enable_google'        => 'yes',
            'google_one_tap'       => 'no',
            'google_key_method'    => 'db',
            'google_client_id'     => 'new-id.apps.googleusercontent.com',
            'google_client_secret' => 'new-secret',
        ]);

        $this->assertNotWPError($result);

        $stored = $this->stored();
        $this->assertEquals('yes', $stored['enabled']);
        $this->assertEquals('no', $stored['google_one_tap']);
        $this->assertEquals('new-secret', $stored['google_client_secret']);
    }
}
