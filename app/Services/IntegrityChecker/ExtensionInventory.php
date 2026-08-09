<?php

namespace FluentAuth\App\Services\IntegrityChecker;

/*
 * What is installed, and which of it can be checked against wordpress.org.
 *
 * Core is easy - there is exactly one of it, and its checksums are a documented API. Plugins
 * and themes are not: a site's wp-content is a mix of things from the .org directory, things
 * bought from a vendor, and things written on the spot, and only the first kind has an
 * official copy to compare against.
 *
 * So this class answers one question per installed item: can we verify it, and if not, why
 * not. The "why not" is kept rather than dropped, because a scan that quietly skips the
 * premium plugins reads as "these are fine" - which is the opposite of what it knows.
 */
class ExtensionInventory
{
    /*
     * The .org directory stamps its own plugins with this prefix in the update transient.
     * A plugin updating from a vendor's own server carries that server's id instead, which is
     * how a paid plugin is told apart from a free one without asking anybody.
     */
    const WP_ORG_PLUGIN_PREFIX = 'w.org/plugins/';

    public static function getTargets()
    {
        return array_merge(self::getPlugins(), self::getThemes());
    }

    /*
     * Every installed plugin, active or not. A dormant plugin is still a directory of PHP
     * that something on the site could include, so it is exactly what a scan should look at.
     */
    public static function getPlugins()
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $installed = get_plugins();
        $known = self::getKnownPluginSlugs();

        $targets = [];

        foreach ($installed as $pluginFile => $data) {
            $version = isset($data['Version']) ? trim($data['Version']) : '';

            /*
             * A single-file plugin (the classic hello.php) lives directly in the plugins
             * directory, so it has no directory of its own. Treated as a one-file target -
             * hashing "its folder" would mean hashing every plugin on the site.
             */
            $isSingleFile = strpos($pluginFile, '/') === false;
            $path = $isSingleFile
                ? WP_PLUGIN_DIR . '/' . $pluginFile
                : WP_PLUGIN_DIR . '/' . dirname($pluginFile);

            $target = [
                'type'        => 'plugin',
                'key'         => $pluginFile,
                'slug'        => isset($known[$pluginFile]) ? $known[$pluginFile] : self::guessSlug($pluginFile),
                'name'        => isset($data['Name']) ? $data['Name'] : $pluginFile,
                'version'     => $version,
                'path'        => $path,
                'rel_path'    => self::toRelativePath($path),
                'single_file' => $isSingleFile,
                'verifiable'  => true,
                'reason'      => ''
            ];

            if (!isset($known[$pluginFile])) {
                $target['verifiable'] = false;
                $target['reason'] = 'not_on_wp_org';
            } elseif (!$version) {
                $target['verifiable'] = false;
                $target['reason'] = 'no_version';
            }

            $targets[] = $target;
        }

        return apply_filters('fluent_auth/integrity_plugin_targets', $targets);
    }

    public static function getThemes()
    {
        $known = self::getKnownThemeSlugs();
        $targets = [];

        foreach (wp_get_themes() as $stylesheet => $theme) {
            /* A theme WordPress itself cannot read has nothing to compare, file by file. */
            if ($theme->errors()) {
                continue;
            }

            $version = trim((string)$theme->get('Version'));
            $path = $theme->get_stylesheet_directory();

            $target = [
                'type'        => 'theme',
                'key'         => $stylesheet,
                'slug'        => $stylesheet,
                'name'        => $theme->get('Name') ? $theme->get('Name') : $stylesheet,
                'version'     => $version,
                'path'        => $path,
                'rel_path'    => self::toRelativePath($path),
                'single_file' => false,
                'verifiable'  => true,
                'reason'      => ''
            ];

            if (!in_array($stylesheet, $known, true)) {
                $target['verifiable'] = false;
                $target['reason'] = 'not_on_wp_org';
            } elseif (!$version) {
                $target['verifiable'] = false;
                $target['reason'] = 'no_version';
            }

            $targets[] = $target;
        }

        return apply_filters('fluent_auth/integrity_theme_targets', $targets);
    }

    /*
     * Plugin file => .org slug, for the plugins the directory recognises.
     *
     * WordPress already asks api.wordpress.org about every installed plugin on its own
     * schedule and files the answers in a transient: the ones with an update under
     * `response`, the ones already current under `no_update`. Reading that is free, and it
     * carries the .org slug, which is not always the folder name.
     */
    protected static function getKnownPluginSlugs()
    {
        $transient = self::getUpdateTransient('plugins');
        $slugs = [];

        foreach (['response', 'no_update'] as $bucket) {
            $items = isset($transient->$bucket) ? (array)$transient->$bucket : [];

            foreach ($items as $pluginFile => $item) {
                $id = is_object($item) && isset($item->id) ? (string)$item->id : '';

                if (strpos($id, self::WP_ORG_PLUGIN_PREFIX) !== 0) {
                    continue; // updated from somewhere other than the .org directory
                }

                $slug = is_object($item) && !empty($item->slug)
                    ? $item->slug
                    : substr($id, strlen(self::WP_ORG_PLUGIN_PREFIX));

                $slugs[$pluginFile] = $slug;
            }
        }

        return $slugs;
    }

    /*
     * The stylesheets the .org directory recognises.
     *
     * Theme entries carry no `id` the way plugin ones do, so membership is presence itself:
     * WordPress sends every installed theme to api.wordpress.org and only hears back about
     * the ones it hosts. Vendor themes that update themselves push into `response` with
     * their own package URL, so those are read off the URL instead.
     */
    protected static function getKnownThemeSlugs()
    {
        $transient = self::getUpdateTransient('themes');
        $slugs = [];

        $items = isset($transient->no_update) ? (array)$transient->no_update : [];
        foreach ($items as $stylesheet => $item) {
            $slugs[] = $stylesheet;
        }

        $items = isset($transient->response) ? (array)$transient->response : [];
        foreach ($items as $stylesheet => $item) {
            $item = (array)$item;
            $package = isset($item['package']) ? (string)$item['package'] : '';
            $url = isset($item['url']) ? (string)$item['url'] : '';

            if (strpos($package, '//downloads.wordpress.org/') !== false
                || strpos($url, '//wordpress.org/themes/') !== false) {
                $slugs[] = $stylesheet;
            }
        }

        return array_values(array_unique($slugs));
    }

    /*
     * The update transient, asking WordPress to fill it in if it is empty.
     *
     * On a site that has just been installed, or has just had its transients flushed, the
     * transient does not exist yet - and an absent transient looks exactly like "none of
     * these plugins are on wordpress.org", which would report the whole site as unverifiable.
     * One update check is the difference between that and a real answer.
     */
    protected static function getUpdateTransient($type)
    {
        $transient = get_site_transient('update_' . $type);

        $hasEntries = is_object($transient)
            && (!empty($transient->response) || !empty($transient->no_update));

        if (!$hasEntries) {
            require_once ABSPATH . 'wp-admin/includes/update.php';

            if ($type === 'themes') {
                wp_update_themes();
            } else {
                wp_update_plugins();
            }

            $transient = get_site_transient('update_' . $type);
        }

        return is_object($transient) ? $transient : new \stdClass();
    }

    protected static function guessSlug($pluginFile)
    {
        if (strpos($pluginFile, '/') === false) {
            return basename($pluginFile, '.php');
        }

        return dirname($pluginFile);
    }

    /*
     * A path relative to the WordPress root, so extension findings sit in the same namespace
     * as core ones and the one ignore list can hold both. wp-content can be moved outside
     * the root entirely, in which case there is no relative form and the absolute path is
     * the stable name.
     */
    public static function toRelativePath($path)
    {
        $path = wp_normalize_path($path);
        $root = wp_normalize_path(ABSPATH);

        if (strpos($path, $root) === 0) {
            return ltrim(substr($path, strlen($root)), '/');
        }

        return $path;
    }

    /* Why an item could not be checked, in words a site owner can act on. */
    public static function getReasonLabel($reason)
    {
        $labels = [
            'not_on_wp_org'         => __('Not from the WordPress.org directory', 'fluent-security'),
            'no_version'            => __('No version number to compare against', 'fluent-security'),
            'version_not_published' => __('Version not on WordPress.org', 'fluent-security'),
            'no_manifest'           => __('No checksums published for this version', 'fluent-security'),
            'download_failed'       => __('The official copy could not be downloaded', 'fluent-security'),
            'unreadable'            => __('The files could not be read on this server', 'fluent-security')
        ];

        return isset($labels[$reason]) ? $labels[$reason] : __('Could not be verified', 'fluent-security');
    }

    /*
     * How much a failure to verify should worry somebody.
     *
     * "Could not be checked" covers two completely different situations and they must not be
     * reported alike:
     *
     *   benign     - the extension is not from the .org directory at all. There was never
     *                anything to compare against. Nearly every site has some of these, and
     *                nothing about it is a finding.
     *
     *   suspicious - the directory *does* publish this extension, but not the version sitting
     *                on this site. Something replaced the files and left a version number the
     *                directory has never released. That is what a backdoored copy carrying a
     *                bumped version header looks like, so it is treated as a finding in its own
     *                right rather than as a gap in coverage.
     *
     *   unknown    - the check itself did not complete: the network failed, the files could not
     *                be read. Says nothing about the extension, and is worth retrying.
     *
     * A pre-release build installed on purpose looks identical to the suspicious case, which is
     * why the row it produces can be marked as expected - see IntegrityHelper::isExtensionIgnored.
     */
    public static function getReasonSeverity($reason)
    {
        if ($reason === 'not_on_wp_org') {
            return 'benign';
        }

        if (in_array($reason, ['version_not_published', 'no_manifest'], true)) {
            return 'suspicious';
        }

        return 'unknown';
    }
}
