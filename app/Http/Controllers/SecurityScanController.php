<?php

namespace FluentAuth\App\Http\Controllers;

use FluentAuth\App\Helpers\Arr;
use FluentAuth\App\Services\IntegrityChecker\Api;
use FluentAuth\App\Services\IntegrityChecker\CheckerService;
use FluentAuth\App\Services\IntegrityChecker\ExtensionChecker;
use FluentAuth\App\Services\IntegrityChecker\ExtensionInventory;
use FluentAuth\App\Services\IntegrityChecker\IntegrityHelper;

class SecurityScanController
{
    public static function getSettings(\WP_REST_Request $request)
    {
        $settings = IntegrityHelper::getSettings();

        if ($settings['last_checked']) {
            $settings['last_checked_human'] = human_time_diff(strtotime($settings['last_checked']), current_time('timestamp'));
        }

        return [
            'settings' => $settings,
            'ignores'  => IntegrityHelper::getIgnoreLists(),
            /*
             * What the last scan made of wp-content. Unlike core's findings these are kept, so
             * arriving on the screen shows the standing picture instead of a blank slate.
             */
            'extension_results' => array_values(IntegrityHelper::getExtensionResults()),
            'extension_summary' => IntegrityHelper::getExtensionSummary()
        ];
    }

    public static function registerSite(\WP_REST_Request $request)
    {
        if ($request->get_param('status') == 'self') {
            $defaults = [
                'status'           => 'self',
                'api_id'           => '',
                'api_key'          => '',
                'last_checked'     => '',
                'account_email_id' => '',
                'is_ok'            => 'yes',
                'auto_scan'        => 'no',
                'scan_interval'    => 'daily',
                'last_report_sent' => ''
            ];

            IntegrityHelper::saveSettings($defaults);

            return [
                'message' => __('Your settings has been saved successfully.', 'fluent-security'),
            ];

        }

        $info = $request->get_param('info');

        if (!is_array($info)) {
            $info = [];
        }

        // Validate the data
        $infoData = [
            'email'     => sanitize_email(Arr::get($info, 'email', '')),
            'full_name' => sanitize_text_field(Arr::get($info, 'full_name', '')),
            'api_id'    => sanitize_text_field(Arr::get($info, 'api_id', '')),
            'api_key'   => sanitize_text_field(Arr::get($info, 'api_key', ''))
        ];

        if (!is_email($infoData['email']) || empty($infoData['full_name'])) {
            return new \WP_Error('invalid_data', __('Please provide a valid email address and full name.', 'fluent-security'), ['status' => 400, 'data' => $infoData]);
        }

        $status = $request->get_param('status');

        $settings = IntegrityHelper::getSettings();
        $isConfirmed = false;
        if ($status == 'unregistered') {
            $apiId = Api::registerSite($infoData);
        } else {
            $infoData['api_id'] = $settings['api_id'];
            $apiId = Api::confirmSite($infoData);
            $isConfirmed = true;
        }

        if (is_wp_error($apiId)) {
            return $apiId;
        }

        if ($isConfirmed) {
            $settings['api_key'] = $infoData['api_key'];
            $settings['status'] = 'active';
        } else {
            $settings['api_id'] = $apiId;
            $settings['status'] = 'pending';
            $settings['account_email_id'] = $infoData['email'];
        }

        IntegrityHelper::saveSettings($settings);

        return [
            'message'  => 'Your site has been successfully registered. Please provide the API token.',
            'settings' => $settings
        ];

    }

    public static function scanSite(\WP_REST_Request $request)
    {
        $settings = IntegrityHelper::getSettings();
        $settings['last_checked'] = current_time('mysql');
        $settings['is_ok'] = 'yes';
        IntegrityHelper::saveSettings($settings);

        try {
            $checkerService = new CheckerService();
        } catch (\Exception $e) {
            return new \WP_Error('invalid_response', __('An error occurred while scanning the site. If you continously get this error, please reconnect the API.', 'fluent-security'), ['status' => 422, 'data' => $e->getMessage()]);
        }

        $scanResults = $checkerService->getScanResults(false);
        $activeChanges = $checkerService->getScanResults(true);

        $hasIssues = array_filter($activeChanges);
        $settings['last_checked'] = current_time('mysql');
        if ($hasIssues) {
            $settings['is_ok'] = 'no';
        }

        IntegrityHelper::saveSettings($settings);

        return [
            'scan_results'  => $scanResults,
            'activeChanges' => $activeChanges,
            'hasIssues'     => !!array_filter($scanResults),
            'willAlert'     => !!array_filter($activeChanges)
        ];
    }

    /*
     * The work list for the plugin and theme phases of a scan.
     *
     * Handed to the browser so it can walk the list one item at a time - see ExtensionChecker
     * for why a single request cannot do all of it - and so the progress it shows is the real
     * count rather than a guess.
     */
    public static function getScanTargets(\WP_REST_Request $request)
    {
        $targets = ExtensionInventory::getTargets();
        $results = IntegrityHelper::getExtensionResults();

        $plugins = [];
        $themes = [];

        foreach ($targets as $target) {
            $target = self::applyKnownFailure($target, $results);

            $item = [
                'type'       => $target['type'],
                'key'        => $target['key'],
                'slug'       => $target['slug'],
                'name'       => $target['name'],
                'version'    => $target['version'],
                'rel_path'   => $target['rel_path'],
                'verifiable' => (bool)$target['verifiable'],
                'reason'     => $target['reason'],
                'reason_label' => $target['reason'] ? ExtensionInventory::getReasonLabel($target['reason']) : ''
            ];

            if ($target['type'] === 'theme') {
                $themes[] = $item;
            } else {
                $plugins[] = $item;
            }
        }

        return [
            'plugins' => $plugins,
            'themes'  => $themes,
            'counts'  => [
                'plugins'            => count($plugins),
                'themes'            => count($themes),
                'verifiable_plugins' => count(array_filter($plugins, function ($p) { return $p['verifiable']; })),
                'verifiable_themes'  => count(array_filter($themes, function ($t) { return $t['verifiable']; }))
            ]
        ];
    }

    /*
     * Carry forward a failure the last scan already established about this exact version.
     *
     * The inventory can only tell that a plugin comes from the .org directory; whether the
     * directory actually publishes the version installed here is something only an attempt can
     * find out. Without remembering that attempt the work list calls such a plugin checkable,
     * the aside counts it as unverified, and the two disagree about the same plugin.
     *
     * Only failures that are a property of the version are carried - a version that is not
     * published will not become published. A download that failed is not one of those: the
     * network being down once is no reason to stop trying.
     */
    protected static function applyKnownFailure($target, $results)
    {
        if (empty($target['verifiable'])) {
            return $target;
        }

        $key = $target['type'] . ':' . $target['key'];
        $result = isset($results[$key]) ? $results[$key] : null;

        if (!$result || !empty($result['verifiable'])) {
            return $target;
        }

        /* A different version now installed deserves its own attempt. */
        if (Arr::get($result, 'version') !== $target['version']) {
            return $target;
        }

        if (!in_array(Arr::get($result, 'reason'), ['version_not_published', 'no_manifest'], true)) {
            return $target;
        }

        $target['verifiable'] = false;
        $target['reason'] = $result['reason'];

        return $target;
    }

    /*
     * Check one plugin or theme. Called once per item while a scan is running.
     */
    public static function scanExtension(\WP_REST_Request $request)
    {
        $type = $request->get_param('type') === 'theme' ? 'theme' : 'plugin';
        $key = $request->get_param('key');

        if (!is_string($key) || empty($key)) {
            return new \WP_Error('invalid_data', __('Please provide the plugin or theme to check.', 'fluent-security'), ['status' => 400]);
        }

        /*
         * The target is taken from the inventory rather than from the request. What gets
         * hashed is a filesystem path and what gets fetched is a wordpress.org slug, and
         * neither should be something the browser can name.
         */
        $target = null;
        foreach (ExtensionInventory::getTargets() as $candidate) {
            if ($candidate['type'] === $type && $candidate['key'] === $key) {
                $target = $candidate;
                break;
            }
        }

        if (!$target) {
            return new \WP_Error('invalid_data', __('That plugin or theme is not installed on this site.', 'fluent-security'), ['status' => 404]);
        }

        $checker = new ExtensionChecker();
        $result = IntegrityHelper::storeExtensionResult($checker->scan($target));

        if (!empty($result['reason'])) {
            $result['reason_label'] = ExtensionInventory::getReasonLabel($result['reason']);
        }

        return [
            'result' => $result
        ];
    }

    public static function toggleIgnore(\WP_REST_Request $request)
    {
        $willRemove = $request->get_param('will_remove') == 'yes';
        $file = $request->get_param('file');

        if (!is_string($file) || empty($file)) {
            return new \WP_Error('invalid_data', __('Please provide a valid file name.', 'fluent-security'), ['status' => 400, 'data' => $file]);
        }

        $isFolder = $request->get_param('is_folder') == 'yes';

        $settings = IntegrityHelper::getIgnoreLists();

        if ($isFolder) {
            $ignoreLists = $settings['folders'];
        } else {
            $ignoreLists = $settings['files'];
        }

        if ($willRemove) {
            $ignoreLists = array_diff($ignoreLists, [$file]);
        } else {
            $ignoreLists[] = $file;
        }

        if ($isFolder) {
            $settings['folders'] = array_values(array_unique($ignoreLists));
        } else {
            $settings['files'] = array_values(array_unique($ignoreLists));
        }

        IntegrityHelper::updateIgnoreLists($settings);

        return [
            'message' => __('Ignore status has been updated.', 'fluent-security'),
            'lists'   => $settings
        ];
    }

    public static function viewFileDiff(\WP_REST_Request $request)
    {
        $fileConfig = $request->get_param('viewing_file');

        if (!$fileConfig || empty($fileConfig['file']) || empty($fileConfig['status'])) {
            return new \WP_Error('invalid_data', __('Please provide a valid file name and status.', 'fluent-security'), ['status' => 400, 'data' => $fileConfig]);
        }

        /* A file inside a plugin or theme is found a different way - see below. */
        if (Arr::get($fileConfig, 'scope') === 'extension') {
            return self::viewExtensionFileDiff($fileConfig);
        }

        $file = $fileConfig['file'];
        $status = $fileConfig['status'];
        $folder = $fileConfig['folder'];

        $validFolders = ['', 'wp-admin', 'wp-includes', WPINC];

        if (!in_array($folder, $validFolders)) {
            return new \WP_Error('invalid_data', __('Invalid folder name.', 'fluent-security'), ['status' => 400, 'data' => $fileConfig]);
        }

        $isInc = $folder == 'wp-includes';

        if ($folder == 'wp-includes') {
            $folder = WPINC;
        }

        if ($folder) {
            // Allow nested paths for wp-admin/wp-includes, realpath() ensures containment
            $filePath = ABSPATH . $folder . '/' . $file;
            $expectedDir = realpath(ABSPATH . $folder);
        } else {
            // Root folder: strip directory components to prevent traversal
            $file = basename($file);
            $filePath = ABSPATH . $file;
            $expectedDir = realpath(ABSPATH);
        }

        $realPath = realpath($filePath);

        if (!$realPath || !$expectedDir || strpos($realPath, $expectedDir . DIRECTORY_SEPARATOR) !== 0) {
            return new \WP_Error('invalid_data', __('This file could not be viewed for security reason.', 'fluent-security'), ['status' => 400, 'data' => $file]);
        }

        $viewable = self::assertViewableFile($filePath, $file);

        if (is_wp_error($viewable)) {
            return $viewable;
        }

        $fileContent = self::readFileContents($filePath);

        $remoteContent = '';
        if ($status == 'modified') {
            $originalRelativePath = str_replace(ABSPATH, '', $filePath);
            if ($isInc) {
                $originalRelativePath = str_replace(WPINC, 'wp-includes', $originalRelativePath);
            }

            $remoteContent = Api::getFileContentFromGithub($originalRelativePath);

            if (is_wp_error($remoteContent)) {
                return new \WP_Error('invalid_data', __('Sorry, we could not compare the changes via Github API.', 'fluent-security'), ['status' => 400]);
            }
        }

        return [
            'filePath'            => str_replace(ABSPATH, '/', $filePath),
            'fileContent'         => $fileContent,
            'hasDiff'             => !!$remoteContent,
            'originalFileContent' => $remoteContent,
        ];

    }

    /*
     * One file inside a plugin or theme, against the copy wordpress.org published.
     *
     * The plugin or theme is looked up in the inventory by the key the browser sends, and the
     * directory to read from comes from that lookup - never from the request. So the only
     * thing the caller controls is a path *within* an installed extension, and realpath()
     * containment settles whether it really is within one.
     */
    protected static function viewExtensionFileDiff($fileConfig)
    {
        $type = Arr::get($fileConfig, 'type') === 'theme' ? 'theme' : 'plugin';
        $key = Arr::get($fileConfig, 'key');
        $file = Arr::get($fileConfig, 'file');
        $status = Arr::get($fileConfig, 'status');

        if (!is_string($key) || !$key) {
            return new \WP_Error('invalid_data', __('Please provide the plugin or theme to view.', 'fluent-security'), ['status' => 400]);
        }

        $target = null;
        foreach (ExtensionInventory::getTargets() as $candidate) {
            if ($candidate['type'] === $type && $candidate['key'] === $key) {
                $target = $candidate;
                break;
            }
        }

        if (!$target) {
            return new \WP_Error('invalid_data', __('That plugin or theme is not installed on this site.', 'fluent-security'), ['status' => 404]);
        }

        /* A single-file plugin *is* the file, so there is no path to append. */
        if (!empty($target['single_file'])) {
            $filePath = $target['path'];
            $expectedDir = realpath(dirname($target['path']));
        } else {
            $filePath = rtrim($target['path'], '/') . '/' . $file;
            $expectedDir = realpath($target['path']);
        }

        $realPath = realpath($filePath);

        if (!$realPath || !$expectedDir || strpos($realPath, $expectedDir . DIRECTORY_SEPARATOR) !== 0) {
            return new \WP_Error('invalid_data', __('This file could not be viewed for security reason.', 'fluent-security'), ['status' => 400]);
        }

        $viewable = self::assertViewableFile($realPath, $file);

        if (is_wp_error($viewable)) {
            return $viewable;
        }

        $remoteContent = '';

        if ($status === 'modified') {
            $remoteContent = Api::getExtensionFileContent($type, $target['slug'], $target['version'], $file);

            if (is_wp_error($remoteContent)) {
                return new \WP_Error('invalid_data', __('Sorry, we could not fetch the original file from WordPress.org.', 'fluent-security'), ['status' => 400]);
            }
        }

        return [
            'filePath'            => '/' . trim($target['rel_path'], '/') . '/' . $file,
            'fileContent'         => self::readFileContents($realPath),
            'hasDiff'             => !!$remoteContent,
            'originalFileContent' => $remoteContent
        ];
    }

    /*
     * Whether a file is one this screen will ever put on the page.
     *
     * Shared by the core and extension viewers so a rule added for one applies to both. Path
     * containment is not checked here - each caller knows the directory a file is supposed to
     * be under, and has already established it.
     */
    protected static function assertViewableFile($filePath, $displayName)
    {
        $sensitivePatterns = [
            'wp-config',
            '.htaccess',
            '.env',
            'debug.log',
            'error_log',
            'php_errorlog',
            '.user.ini',
            '.php.ini',
            'php.ini',
            '.ftpconfig',
            '.ssh',
        ];

        $backupExtensions = ['.bak', '.back', '.backup', '.old', '.orig', '.save', '.swp', '.tmp', '.copy', '~'];

        $fileLower = strtolower($displayName);

        foreach ($sensitivePatterns as $pattern) {
            if (strpos($fileLower, $pattern) !== false) {
                return new \WP_Error('invalid_data', __('This file could not be viewed.', 'fluent-security'), ['status' => 400, 'data' => $displayName]);
            }
        }

        foreach ($backupExtensions as $ext) {
            if (substr($fileLower, -strlen($ext)) === $ext) {
                return new \WP_Error('invalid_data', __('This file could not be viewed.', 'fluent-security'), ['status' => 400, 'data' => $displayName]);
            }
        }

        if (!file_exists($filePath)) {
            return new \WP_Error('invalid_data', __('This file could not be viewed.', 'fluent-security'), ['status' => 400, 'data' => $displayName]);
        }

        $maxFileSize = 2 * 1024 * 1024; // 2MB
        if (filesize($filePath) > $maxFileSize) {
            return new \WP_Error('invalid_data', __('This file is too large to be viewed.', 'fluent-security'), ['status' => 400, 'data' => $displayName]);
        }

        if (!is_readable($filePath)) {
            return new \WP_Error('invalid_data', __('This file is not readable.', 'fluent-security'), ['status' => 400, 'data' => $displayName]);
        }

        return true;
    }

    protected static function readFileContents($filePath)
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;

        return $wp_filesystem->get_contents($filePath);
    }

    public static function updateScheduleScan(\WP_REST_Request $request)
    {
        $interval = $request->get_param('scan_interval');
        $enabled = $request->get_param('auto_scan') == 'yes';

        if (!is_string($interval) || empty($interval)) {
            return new \WP_Error('invalid_data', __('Please provide a valid interval.', 'fluent-security'), ['status' => 400, 'data' => $interval]);
        }

        $globalSettings = IntegrityHelper::getSettings();

        $globalSettings['auto_scan'] = $enabled ? 'yes' : 'no';
        $globalSettings['scan_interval'] = $interval == 'hourly' ? 'hourly' : 'daily';

        IntegrityHelper::saveSettings($globalSettings);

        return [
            'message'  => __('Schedule scan has been updated.', 'fluent-security'),
            'settings' => $globalSettings
        ];
    }

    public static function resetIgnores(\WP_REST_Request $request)
    {
        IntegrityHelper::updateIgnoreLists([
            'files'   => [],
            'folders' => []
        ]);

        return [
            'message' => __('Ignore lists have been reset successfully.', 'fluent-security')
        ];
    }

    public static function resetApi(\WP_REST_Request $request)
    {
        Api::disableApi();

        $settings = IntegrityHelper::getSettings();

        $settings['status'] = 'unregistered';
        $settings['api_id'] = '';
        $settings['api_key'] = '';
        $settings['auto_scan'] = 'no';
        $settings['scan_interval'] = 'daily';
        $settings['account_email_id'] = '';

        IntegrityHelper::saveSettings($settings);

        return [
            'message'  => __('API has been reset successfully.', 'fluent-security'),
            'settings' => $settings
        ];
    }
}
