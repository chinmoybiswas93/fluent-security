<?php

namespace FluentAuth\App\Hooks\Handlers;

use FluentAuth\App\Services\TwoFa\TotpTwoFaMethod;

/**
 * Holds users whose role requires an authenticator app at the door until they have one.
 *
 * The gate is placed after login rather than during it, and that is the important
 * decision here. Enrolling mid-login would mean pairing a new second factor for
 * whoever just typed the password - so an attacker who had only that could register
 * their own authenticator and lock the real owner out. By the time this runs the user
 * has satisfied every factor the account already had, so the secret is being handed to
 * someone who has already proven they are the account holder.
 *
 * It gates the admin area only. Requiring a second factor to read the front end of a
 * site would be a strange thing to do to a subscriber, and the admin area is what the
 * policy is actually protecting.
 */
class TotpEnforcementHandler
{
    public function register()
    {
        add_action('admin_init', [$this, 'maybeForceEnrollment'], 1);
        add_action('admin_notices', [$this, 'renderNotice']);
    }

    /**
     * @return void
     */
    public function maybeForceEnrollment()
    {
        if (!$this->needsEnrollment()) {
            return;
        }

        // Already where they need to be; redirecting again would be a loop.
        if ($this->isEnrollmentScreen()) {
            return;
        }

        /*
         * Sent to the standalone screen rather than to their profile. The profile screen
         * is inside the admin area, which is exactly what this rule is holding shut - and
         * on a site that keeps a role out of wp-admin altogether, being sent there means
         * being bounced straight back out again with nothing set up.
         *
         * They came here trying to use the admin area, so that is where Continue returns
         * them to once they are done.
         */
        wp_safe_redirect(TotpSetupPageHandler::getUrl(admin_url()));
        exit();
    }

    /**
     * @return void
     */
    public function renderNotice()
    {
        if (!$this->needsEnrollment() || !$this->isEnrollmentScreen()) {
            return;
        }

        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('Two-factor authentication is required for your account.', 'fluent-security'); ?></strong>
                <?php esc_html_e('Set up an authenticator app below to continue using the admin area.', 'fluent-security'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Whether this request belongs to someone who owes an authenticator app.
     *
     * Public because it is the whole of the decision - the redirect around it is two
     * lines - and a rule that can lock an administrator out of their own site should be
     * something tests can ask about directly.
     *
     * @return bool
     */
    public function needsEnrollment()
    {
        if (!is_user_logged_in()) {
            return false;
        }

        /*
         * Only ordinary page loads. A redirect sent in reply to an ajax call, a cron
         * run or a REST request breaks the caller rather than reaching anybody, and
         * these are not how someone browses the admin area anyway.
         */
        if (wp_doing_ajax() || wp_doing_cron() || $this->isRestRequest()) {
            return false;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        if (defined('WP_CLI') && WP_CLI) {
            return false;
        }

        $user = wp_get_current_user();

        if (TotpTwoFaMethod::isEnrolled($user)) {
            return false;
        }

        return TotpTwoFaMethod::isRequiredForUser($user);
    }

    /**
     * The profile screen still carries a setup form and still posts back to itself, so
     * somebody who went there under their own steam is left to finish there rather than
     * being pulled off the page mid-enrollment.
     *
     * The standalone screen needs no exemption: it lives on wp-login.php, where
     * admin_init never runs.
     *
     * @return bool
     */
    private function isEnrollmentScreen()
    {
        global $pagenow;

        return $pagenow === 'profile.php';
    }

    /**
     * @return bool
     */
    private function isRestRequest()
    {
        return (defined('REST_REQUEST') && REST_REQUEST)
            || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST);
    }
}
