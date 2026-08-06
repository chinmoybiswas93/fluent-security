<?php

namespace FluentAuth\Tests\Unit;

use FluentAuth\App\Http\Controllers\SettingsController;

class SettingsControllerTest extends BaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        update_option('__fls_auth_settings', [
            'disable_xmlrpc' => 'no',
            'enable_auth_logs' => 'yes',
            'login_try_limit' => 5,
            'login_try_timing' => 30,
            'auto_delete_logs_day' => 30,
            'disable_app_login' => 'no',
            'disable_users_rest' => 'no',
            'secure_signup_form' => 'yes',
            'notification_user_roles' => [],
            'notify_on_blocked' => 'no',
            'notification_email' => '{admin_email}',
            'digest_summary' => '',
            'magic_login' => 'no',
            'magic_restricted_roles' => [],
            'magic_link_primary' => 'no',
            'email2fa' => 'no',
            'email2fa_roles' => ['administrator', 'editor', 'author'],
            'disable_admin_bar' => 'no',
            'disable_bar_roles' => ['subscriber'],
        ]);

        update_option('__fls_auth_forms_settings', [
            'enabled' => 'no',
            'login_redirects' => 'no',
            'default_login_redirect' => '',
            'default_logout_redirect' => '',
            'redirect_rules' => []
        ]);
    }

    public function testGetSettings()
    {
        $request = new \WP_REST_Request();

        $result = SettingsController::getSettings($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('settings', $result);
        $this->assertArrayHasKey('user_roles', $result);
        $this->assertArrayHasKey('low_level_roles', $result);
    }

    public function testUpdateSettings()
    {
        $request = new \WP_REST_Request();
        $request->set_param('settings', [
            'disable_xmlrpc' => 'yes',
            'enable_auth_logs' => 'yes',
            'login_try_limit' => 10,
            'login_try_timing' => 30,
            'email2fa' => 'no',
            'email2fa_roles' => [],
        ]);

        $result = SettingsController::updateSettings($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testUpdateSettingsInvalidData()
    {
        $request = new \WP_REST_Request();
        $request->set_param('settings', [
            'enable_auth_logs' => 'yes',
            'login_try_limit' => 0,
            'login_try_timing' => 0,
            'email2fa' => 'no',
        ]);

        $result = SettingsController::updateSettings($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    public function testGetAuthFormSettings()
    {
        $request = new \WP_REST_Request();

        $result = SettingsController::getAuthFormSettings($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('settings', $result);
    }

    public function testSaveAuthFormSettings()
    {
        $request = new \WP_REST_Request();
        $request->set_param('settings', [
            'enabled' => 'yes',
        ]);

        $result = SettingsController::saveAuthFormSettings($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testGetAuthCustomizerSetting()
    {
        $request = new \WP_REST_Request();

        $result = SettingsController::getAuthCustomizerSetting($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('settings', $result);
    }

    public function testSaveAuthCustomizerSetting()
    {
        $request = new \WP_REST_Request();
        $request->set_param('settings', [
            'login' => [
                'banner' => [
                    'title' => 'Test Title',
                    'hidden' => false,
                    'description' => 'Test Desc',
                ]
            ]
        ]);

        $result = SettingsController::saveAuthCustomizerSetting($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testUploadImageNoFile()
    {
        $request = new \WP_REST_Request();
        $_FILES = ['file' => []];

        $result = SettingsController::uploadImage($request);

        $this->assertInstanceOf(\WP_Error::class, $result);

        $_FILES = [];
    }

    public function testUpdateSettingsWithSocialLogin()
    {
        $request = new \WP_REST_Request();
        $request->set_param('settings', [
            'enable_auth_logs' => 'yes',
            'login_try_limit' => 5,
            'login_try_timing' => 30,
            'email2fa' => 'no',
            'email2fa_roles' => [],
        ]);

        $result = SettingsController::updateSettings($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testGetSettingsWithPermissions()
    {
        $request = new \WP_REST_Request();

        $result = SettingsController::getSettings($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('settings', $result);
        $this->assertArrayHasKey('disable_xmlrpc', $result['settings']);
        $this->assertArrayHasKey('login_try_limit', $result['settings']);
        $this->assertArrayHasKey('login_try_timing', $result['settings']);
    }
}
