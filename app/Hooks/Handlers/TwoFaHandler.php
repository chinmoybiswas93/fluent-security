<?php

namespace FluentAuth\App\Hooks\Handlers;

use FluentAuth\App\Helpers\Arr;
use FluentAuth\App\Helpers\Helper;
use FluentAuth\App\Services\TwoFa\EmailTwoFaMethod;
use FluentAuth\App\Services\TwoFa\TwoFaService;

/**
 * Owns the login flow around a second factor.
 *
 * The proof itself belongs to a method (see BaseTwoFaMethod) - this class only handles
 * what every method needs identically: raising the pending row, carrying the redirect
 * intent and the remember-me flag across the challenge, capping guesses, reporting
 * failures to the attempt limit and completing the sign in.
 */
class TwoFaHandler
{
    /**
     * How many times a single issued challenge may be guessed before it is burned.
     * Matches AuthService::verifyTokenHash so both flows behave the same way.
     */
    const MAX_VERIFY_ATTEMPTS = 5;

    /**
     * How long a raised challenge stays answerable, in seconds.
     */
    const PENDING_TIMEOUT = 600;

    /**
     * Codes issued because email 2FA is switched on for the user's role.
     *
     * @deprecated Use EmailTwoFaMethod::getKey(). Kept because it is a published value.
     */
    const USE_TYPE = 'email_2_fa';

    /**
     * Codes issued because the account itself is under attack. Recorded separately so
     * the code stays usable even where email 2FA is not otherwise enabled.
     *
     * @deprecated Use EmailTwoFaMethod::getChallengeKey().
     */
    const CHALLENGE_USE_TYPE = '2fa_challenge';

    private $challengeCache = [];

    /**
     * @param $user \WP_User
     * @return bool
     */
    private function isChallengeRequired($user)
    {
        if (!$user instanceof \WP_User) {
            return false;
        }

        if (!isset($this->challengeCache[$user->ID])) {
            $this->challengeCache[$user->ID] = (bool)apply_filters('fluent_auth/2fa_challenge_required', false, $user);
        }

        return $this->challengeCache[$user->ID];
    }

    public function register()
    {
        add_action('fluent_auth/login_attempts_checked', [$this, 'maybe2FaRedirect'], 1, 1);
        add_action('login_form_fls_2fa_email', [$this, 'render2FaForm'], 1);
        add_action('wp_ajax_nopriv_fluent_auth_2fa_email', [$this, 'verify2FaEmailCode']);
        add_action('wp_ajax_fluent_auth_2fa_email', function () {
            $hash = sanitize_text_field(Arr::get($_REQUEST, 'login_hash'));

            $logHash = flsDb()->table('fls_login_hashes')
                ->where('login_hash', $hash)
                ->whereIn('use_type', TwoFaService::getAllUseTypes())
                ->orderBy('id', 'DESC')
                ->first();

            $user = get_user_by('ID', get_current_user_id());
            $redirectTo = admin_url();
            if ($logHash && $logHash->redirect_intend) {
                $redirectTo = $logHash->redirect_intend;
                $redirectTo = apply_filters('login_redirect', $redirectTo, $logHash->redirect_intend, $user);
            }

            wp_send_json([
                'redirect' => $redirectTo
            ]);
        });
    }

    public function render2FaForm()
    {
        if (!isset($_GET['fls_2fa']) || $_GET['fls_2fa'] != 'email') {
            return;
        }

        /*
         * The pending row is the authority on whether a challenge is outstanding. A
         * challenge can be raised on a site where the method is otherwise off, so
         * re-checking the settings here would dead end that login.
         */
        $logHash = $this->getPendingRow(Arr::get($_REQUEST, 'login_hash'));

        if (!$logHash) {
            return false;
        }

        $method = TwoFaService::getMethodByUseType($logHash->use_type);

        if (!$method) {
            return false;
        }

        login_header(__('Provide Login Code', 'fluent-security'), '', null);
        do_action('fls_load_login_helper');
        echo $method->renderForm($_REQUEST); // PHPCS:Ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        login_footer();
        exit();
    }

    public function maybe2FaRedirect($user)
    {
        // If it's an ajax call and not our own ajax calls then we will just return it
        // Until we get a better work-around for other plugins
        if (wp_doing_ajax() && empty($_REQUEST['_is_fls_form'])) {
            return false;
        }

        $return = $this->sendAndGet2FaConfirmFormUrl($user, 'both');

        if (!$return) {
            return false;
        }

        if (wp_doing_ajax()) {
            wp_send_json([
                'load_2fa'    => 'yes',
                'two_fa_form' => $this->get2FaFormHtml($return)
            ]);
        }

        wp_safe_redirect($return['redirect_to']);
        exit();
    }

    /**
     * Raises a second factor challenge for this user, if one is still owed.
     *
     * @param $user \WP_User
     * @param $return string 'url' or 'both'
     * @param $redirectIntend string|null explicit intent for callers that do not carry
     *                                    it in $_REQUEST, such as social login
     * @return array|string|false
     */
    public function sendAndGet2FaConfirmFormUrl($user, $return = 'url', $redirectIntend = null)
    {
        $challengeRequired = $this->isChallengeRequired($user);

        $method = TwoFaService::getRequiredMethod($user, null, $challengeRequired);

        if (!$method) {
            return false;
        }

        $string = $user->ID . '-' . wp_generate_uuid4() . mt_rand(1, 99999999);
        $hash = wp_hash_password($string);
        $hash = sanitize_title($hash, '', 'display');
        $hash .= $user->ID . '-' . time();

        if ($redirectIntend === null) {
            $redirectIntend = '';
            if (isset($_REQUEST['redirect_to'])) {
                $redirectIntend = esc_url($_REQUEST['redirect_to']);
            }
        }

        if (isset($_REQUEST['rememberme'])) {
            $hash .= '-auth';
        }

        $challenge = $method->prepareChallenge($user);

        $data = array(
            'login_hash'      => $hash,
            'user_id'         => $user->ID,
            'status'          => 'issued',
            'ip_address'      => Helper::getIp(),
            'redirect_intend' => $redirectIntend,
            'use_type'        => $challengeRequired ? $method->getChallengeKey() : $method->getKey(),
            'valid_till'      => date('Y-m-d H:i:s', current_time('timestamp') + self::PENDING_TIMEOUT),
            'created_at'      => current_time('mysql'),
            'updated_at'      => current_time('mysql')
        );

        $data = array_merge($data, (array)Arr::get($challenge, 'columns', []));

        flsDb()->table('fls_login_hashes')
            ->insert($data);

        $method->dispatchChallenge($user, $challenge, [
            'login_hash'  => $hash,
            'redirect_to' => $redirectIntend,
            'row'         => $data
        ]);

        $redirectTo = add_query_arg([
            'fls_2fa'    => 'email',
            'login_hash' => $hash,
            'action'     => 'fls_2fa_email'
        ], wp_login_url());

        if ($return === 'url') {
            return $redirectTo;
        }

        return [
            'redirect_to' => $redirectTo,
            'login_hash'  => $hash
        ];
    }

    public function verify2FaEmailCode()
    {
        $hash = sanitize_text_field(Arr::get($_REQUEST, 'login_hash'));

        if (!$hash) {
            wp_send_json([
                'message' => __('Please provide a valid login code', 'fluent-security')
            ], 422);
        }

        $logHash = flsDb()->table('fls_login_hashes')
            ->where('login_hash', $hash)
            ->whereIn('use_type', TwoFaService::getAllUseTypes())
            ->orderBy('id', 'DESC')
            ->first();

        if (!$logHash) {
            wp_send_json([
                'message' => __('Your provided code or url is not valid', 'fluent-security')
            ], 422);
        }

        $method = TwoFaService::getMethodByUseType($logHash->use_type);
        $user = get_user_by('ID', $logHash->user_id);

        /*
         * Every one of these has to be settled BEFORE the proof is compared. Checking
         * them afterwards (as this used to) means the attempt cap only ever applies to
         * a code that already matched, so a wrong code could be retried indefinitely.
         */
        if (!$user || !$method || $logHash->status != 'issued' || strtotime($logHash->created_at) < current_time('timestamp') - self::PENDING_TIMEOUT) {
            wp_send_json([
                'message' => __('Sorry, your login code has been expired. Please try to login again', 'fluent-security')
            ], 422);
        }

        if ($logHash->used_count >= self::MAX_VERIFY_ATTEMPTS) {
            $this->invalidate2FaCode($logHash);

            wp_send_json([
                'message' => __('Too many invalid attempts for this login code. Please try to login again', 'fluent-security')
            ], 422);
        }

        /*
         * A challenge authorises itself: it was raised precisely because the account was
         * under attack, so it has to keep working even if the attack has since died down
         * or the method is not enabled for this role at all.
         */
        if ($logHash->use_type !== $method->getChallengeKey() && !$method->isAvailableForUser($user)) {
            wp_send_json([
                'message' => __('Sorry, You can not use this verification method', 'fluent-security')
            ], 422);
        }

        $verified = $method->verifyProof($user, $logHash, $_REQUEST);

        if (is_wp_error($verified)) {
            wp_send_json([
                'message' => $verified->get_error_message()
            ], 422);
        }

        if (!$verified) {
            $this->recordFailedAttempt($logHash, $user, $method);

            wp_send_json([
                'message' => __('Your provided code is not valid. Please try again', 'fluent-security')
            ], 422);
        }

        remove_action('fluent_auth/login_attempts_checked', [$this, 'maybe2FaRedirect'], 1);

        // They already produced the proof, so the attempt limit must not block them.
        Helper::setTokenVerifiedLogin(true);

        add_filter('authenticate', array($this, 'allowProgrammaticLogin'), 10, 3);    // hook in earlier than other callbacks to short-circuit them
        $user = wp_signon(array(
                'user_login'    => $user->user_login,
                'user_password' => '',
                'remember'      => (bool)strpos($logHash->login_hash, '-auth')
            )
        );

        remove_filter('authenticate', array($this, 'allowProgrammaticLogin'), 10);

        Helper::setTokenVerifiedLogin(false);

        if ($user instanceof \WP_User) {
            wp_set_current_user($user->ID, $user->user_login);
            if (is_user_logged_in()) {
                flsDb()->table('fls_login_hashes')
                    ->where('id', $logHash->id)
                    ->update([
                        'status'             => 'used',
                        'success_ip_address' => Helper::getIp()
                    ]);

                $redirectTo = $logHash->redirect_intend;
                if (!$redirectTo) {
                    $redirectTo = admin_url();
                }

                Helper::setLoginMedia($method->getLoginMedia());

                $redirectTo = apply_filters('login_redirect', $redirectTo, $logHash->redirect_intend, $user);

                wp_send_json([
                    'redirect' => $redirectTo
                ]);
            }
        }

        wp_send_json([
            'message' => __('There has an error when log you in. Please try to login again', 'fluent-security')
        ], 422);
    }

    public function allowProgrammaticLogin($user, $username, $password)
    {
        return get_user_by('login', $username);
    }

    /**
     * Counts a wrong answer and burns the challenge once the cap is reached.
     *
     * @param $logHash object
     * @param $user \WP_User
     * @param $method \FluentAuth\App\Services\TwoFa\BaseTwoFaMethod
     * @return void
     */
    private function recordFailedAttempt($logHash, $user, $method)
    {
        $usedCount = $logHash->used_count + 1;

        $update = [
            'used_count' => $usedCount,
            'updated_at' => current_time('mysql')
        ];

        // Burn the challenge once the cap is reached, it must not stay guessable.
        if ($usedCount >= self::MAX_VERIFY_ATTEMPTS) {
            $update['status'] = 'failed';
        }

        flsDb()->table('fls_login_hashes')
            ->where('id', $logHash->id)
            ->update($update);

        /*
         * The first factor already succeeded to get here, so nothing has been recorded
         * as a failure yet. Reporting it makes these attempts visible to the IP attempt
         * limit - without that an attacker who has the password can just log in again
         * for a fresh challenge and keep guessing forever.
         */
        Helper::setLoginMedia($method->getLoginMedia());

        do_action('wp_login_failed', $user->user_login, new \WP_Error(
            'fls_invalid_2fa_code',
            __('Invalid two factor authentication code', 'fluent-security')
        ));
    }

    /**
     * @param $hash string
     * @return object|null
     */
    private function getPendingRow($hash)
    {
        $hash = sanitize_text_field($hash);

        if (!$hash) {
            return null;
        }

        return flsDb()->table('fls_login_hashes')
            ->where('login_hash', $hash)
            ->whereIn('use_type', TwoFaService::getAllUseTypes())
            ->where('status', 'issued')
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Burns a challenge so it can no longer be guessed.
     *
     * @param $logHash object
     * @return void
     */
    private function invalidate2FaCode($logHash)
    {
        if ($logHash->status === 'failed') {
            return;
        }

        flsDb()->table('fls_login_hashes')
            ->where('id', $logHash->id)
            ->update([
                'status'     => 'failed',
                'updated_at' => current_time('mysql')
            ]);
    }

    /**
     * @param $data array
     * @return string
     */
    private function get2FaFormHtml($data = [])
    {
        $logHash = $this->getPendingRow(Arr::get($data, 'login_hash'));

        $method = $logHash ? TwoFaService::getMethodByUseType($logHash->use_type) : null;

        if (!$method) {
            $method = new EmailTwoFaMethod();
        }

        return $method->renderForm($data);
    }
}
