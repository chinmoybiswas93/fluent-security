<?php

namespace FluentAuth\App\Hooks\Handlers;

use FluentAuth\App\Helpers\Arr;
use FluentAuth\App\Helpers\Helper;
use FluentAuth\App\Services\SmartCodeParser;
use FluentAuth\App\Services\SystemEmailService;

class TwoFaHandler
{
    /**
     * How many times a single emailed login code may be guessed before it is burned.
     * Matches AuthService::verifyTokenHash so both flows behave the same way.
     */
    const MAX_VERIFY_ATTEMPTS = 5;

    /**
     * Codes issued because email 2FA is switched on for the user's role.
     */
    const USE_TYPE = 'email_2_fa';

    /**
     * Codes issued because the account itself is under attack. Recorded separately so
     * the code stays usable even where email 2FA is not otherwise enabled.
     */
    const CHALLENGE_USE_TYPE = '2fa_challenge';

    private $challengeCache = [];

    /**
     * @return array
     */
    private function twoFaUseTypes()
    {
        return [self::USE_TYPE, self::CHALLENGE_USE_TYPE];
    }

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
                ->whereIn('use_type', $this->twoFaUseTypes())
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
         * A challenge can be raised on a site where email 2FA is otherwise off, so the
         * form has to render for a pending challenge too or the login dead ends here.
         */
        if (!$this->isEnabled() && !$this->hasPendingChallenge(Arr::get($_REQUEST, 'login_hash'))) {
            return false;
        }

        login_header(__('Provide Login Code', 'fluent-security'), '', null);
        do_action('fls_load_login_helper');
        echo $this->get2FaFormHtml($_REQUEST); // PHPCS:Ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

    public function sendAndGet2FaConfirmFormUrl($user, $return = 'url')
    {
        if (!$this->isEnabled($user)) {
            return false;
        }

        try {
            $twoFaCode = random_int(100123, 900987);
        } catch (\Exception $e) {
            $twoFaCode = mt_rand(100123, 900987);
        }

        $twoFaCode = (string)$twoFaCode;

        $string = $user->ID . '-' . wp_generate_uuid4() . mt_rand(1, 99999999);
        $hash = wp_hash_password($string);
        $hash = sanitize_title($hash, '', 'display');
        $hash .= $user->ID . '-' . time();

        $redirectIntend = '';
        if (isset($_REQUEST['redirect_to'])) {
            $redirectIntend = esc_url($_REQUEST['redirect_to']);
        }

        if (isset($_REQUEST['rememberme'])) {
            $hash .= '-auth';
        }

        $data = array(
            'login_hash'       => $hash,
            'user_id'          => $user->ID,
            'status'           => 'issued',
            'ip_address'       => Helper::getIp(),
            'redirect_intend'  => $redirectIntend,
            'use_type'         => $this->isChallengeRequired($user) ? self::CHALLENGE_USE_TYPE : self::USE_TYPE,
            'two_fa_code_hash' => wp_hash_password($twoFaCode),
            'valid_till'       => date('Y-m-d H:i:s', current_time('timestamp') + 10 * 60),
            'created_at'       => current_time('mysql'),
            'updated_at'       => current_time('mysql')
        );

        flsDb()->table('fls_login_hashes')
            ->insert($data);

        $autoLoginUrl = add_query_arg([
            'fls_2fa'    => 'email',
            'login_hash' => $hash,
            'action'     => 'fls_2fa_email',
            'auto_code'  => $twoFaCode
        ], wp_login_url());

        $data['two_fa_code'] = $twoFaCode;

        /*
         * The code row is always created and the caller always gets a redirect, so 2FA
         * stays enforced no matter what. Only the outgoing email is throttled - someone
         * holding the password can otherwise trigger an unlimited number of them.
         */
        if (!$this->hasReachedCodeRequestLimit($user)) {
            $this->send2FaEmail($data, $user, $autoLoginUrl);
        }

        do_action('fls_send_2fa_code', $data, $user, $autoLoginUrl);

        if ($return === 'url') {
            return add_query_arg([
                'fls_2fa'    => 'email',
                'login_hash' => $hash,
                'action'     => 'fls_2fa_email'
            ], wp_login_url());
        }

        return [
            'redirect_to' => add_query_arg([
                'fls_2fa'    => 'email',
                'login_hash' => $hash,
                'action'     => 'fls_2fa_email'
            ], wp_login_url()),
            'login_hash'  => $hash
        ];
    }

    public function verify2FaEmailCode()
    {
        $code = sanitize_text_field(Arr::get($_REQUEST, 'login_passcode'));
        $hash = sanitize_text_field(Arr::get($_REQUEST, 'login_hash'));

        if (!$code || !$hash) {
            wp_send_json([
                'message' => __('Please provide a valid login code', 'fluent-security')
            ], 422);
        }

        $logHash = flsDb()->table('fls_login_hashes')
            ->where('login_hash', $hash)
            ->whereIn('use_type', $this->twoFaUseTypes())
            ->orderBy('id', 'DESC')
            ->first();

        if (!$logHash) {
            wp_send_json([
                'message' => __('Your provided code or url is not valid', 'fluent-security')
            ], 422);
        }

        $user = get_user_by('ID', $logHash->user_id);

        /*
         * Every one of these has to be settled BEFORE the code is compared. Checking
         * them afterwards (as this used to) means the attempt cap only ever applies to
         * a code that already matched, so a wrong code could be retried indefinitely.
         */
        if (!$user || $logHash->status != 'issued' || strtotime($logHash->created_at) < current_time('timestamp') - 600) {
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
         * A challenge code authorises itself: it was issued precisely because the
         * account was under attack, so it has to keep working even if the attack has
         * since died down or email 2FA is not enabled for this role at all.
         */
        if ($logHash->use_type !== self::CHALLENGE_USE_TYPE && !$this->isEnabled($user)) {
            wp_send_json([
                'message' => __('Sorry, You can not use this verification method', 'fluent-security')
            ], 422);
        }

        if (!wp_check_password($code, $logHash->two_fa_code_hash)) {
            $usedCount = $logHash->used_count + 1;

            $update = [
                'used_count' => $usedCount,
                'updated_at' => current_time('mysql')
            ];

            // Burn the code once the cap is reached, it must not stay guessable.
            if ($usedCount >= self::MAX_VERIFY_ATTEMPTS) {
                $update['status'] = 'failed';
            }

            flsDb()->table('fls_login_hashes')
                ->where('id', $logHash->id)
                ->update($update);

            /*
             * The first factor already succeeded to get here, so nothing has been
             * recorded as a failure yet. Reporting it makes these attempts visible to
             * the IP attempt limit - without that an attacker who has the password can
             * just log in again for a fresh code and keep guessing forever.
             */
            Helper::setLoginMedia('two_factor_email');

            do_action('wp_login_failed', $user->user_login, new \WP_Error(
                'fls_invalid_2fa_code',
                __('Invalid two factor authentication code', 'fluent-security')
            ));

            wp_send_json([
                'message' => __('Your provided code is not valid. Please try again', 'fluent-security')
            ], 422);
        }

        remove_action('fluent_auth/login_attempts_checked', [$this, 'maybe2FaRedirect'], 1);

        // They read the code out of their inbox, so the attempt limit must not block it.
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

                Helper::setLoginMedia('two_factor_email');

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


    private function send2FaEmail($data, $user, $autoLoginUrl = false)
    {

        $emailData = $this->getCustomizedEmailSubjectBody($data, $user, $autoLoginUrl);

        if (empty($emailData['subject']) || empty($emailData['body'])) {
            $blogName = html_entity_decode(get_bloginfo('name'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            /* translators: %1$1s: Site Name, %2$d: verification code */
            $emailSubject = sprintf(__('Your Login code for %1$1s - %2$d', 'fluent-security'), $blogName, $data['two_fa_code']);

            $emailLines = [
                /* translators: %s: User's Display Name  */
                sprintf(__('Hello %s,', 'fluent-security'), $user->display_name),
                /* translators: %s: Site Name  */
                sprintf(__('Someone requested to login to %s and here is the Login code that you can use in the login form', 'fluent-security'), $blogName),
                '<b>' . __('Your Login Code: ', 'fluent-security') . '</b>',
                '<p style="font-size: 22px;border: 2px dashed #555454;padding: 5px 10px;text-align: center;background: #fffaca;letter-spacing: 7px;color: #555454;display:block;">' . $data['two_fa_code'] . '</p>',
                /* translators: %d: Minute  */
                sprintf(__('This code will expire in %d minutes and can only be used once.', 'fluent-security'), 10),
                ' ',
                '<hr />'
            ];

            $callToAction = false;

            if ($autoLoginUrl) {
                $emailLines[] = ' ';
                $emailLines[] = __('You can also login by clicking the following button', 'fluent-security');
                $callToAction = [
                    /* translators: %s: Site Name  */
                    'btn_text' => sprintf(__('Sign in to %s', 'fluent-security'), $blogName),
                    'url'      => $autoLoginUrl
                ];
            }

            $footerLines = [
                ' ',
                __('If you did not make this request, you can safely ignore this email.', 'fluent-security')
            ];

            $emailBody = '';
            $emailBody .= Helper::loadView('magic_login.header', [
                'pre_header' => $emailSubject
            ]);

            $emailBody .= Helper::loadView('magic_login.line_block', [
                'lines' => $emailLines
            ]);

            if ($callToAction) {
                $emailBody .= Helper::loadView('magic_login.call_to_action', $callToAction);
            }

            $emailBody .= Helper::loadView('magic_login.line_block', [
                'lines' => $footerLines
            ]);

            $emailBody .= Helper::loadView('magic_login.footer', []);

            $emailData = [
                'subject' => $emailSubject,
                'body'    => $emailBody
            ];
        }

        return \wp_mail($user->user_email, $emailData['subject'], $emailData['body'], array(
            'Content-Type: text/html; charset=UTF-8'
        ));
    }


    private function getCustomizedEmailSubjectBody($data, $user, $autoLoginUrl = false)
    {
        $customSetting = SystemEmailService::getEmailSettingsByType('two_fa_email_to_user');

        if (Arr::get($customSetting, 'status', '') !== 'active') {
            return [
                'subject' => '',
                'body'    => ''
            ];
        }

        $subject = Arr::get($customSetting, 'email.subject', '');
        $body = Arr::get($customSetting, 'email.body', '');


        $replaces = [
            '{{user.two_fa_code}}'  => $data['two_fa_code'],
            '##user.two_fa_code##'  => $data['two_fa_code'],
            '##user.secure_signin_url##' => $autoLoginUrl,
            '{{user.secure_signin_url}}' => $autoLoginUrl,
        ];

        $subject = strtr($subject, $replaces);
        $body = strtr($body, $replaces);

        $body = SystemEmailService::withHtmlTemplate($body, null, $user);

        $body = (new SmartCodeParser())->parse($body, $user);
        $subject = (new SmartCodeParser())->parse($subject, $user);

        return [
            'subject' => $subject,
            'body'    => $body
        ];
    }

    public function allowProgrammaticLogin($user, $username, $password)
    {
        return get_user_by('login', $username);
    }

    /**
     * @param $hash string
     * @return bool
     */
    private function hasPendingChallenge($hash)
    {
        $hash = sanitize_text_field($hash);

        if (!$hash) {
            return false;
        }

        return (bool)flsDb()->table('fls_login_hashes')
            ->where('login_hash', $hash)
            ->where('use_type', self::CHALLENGE_USE_TYPE)
            ->where('status', 'issued')
            ->first();
    }

    /**
     * Burns a login code so it can no longer be guessed.
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
     * Whether we have already emailed this user enough codes for now.
     *
     * Keyed on the user rather than the IP - the point is protecting their inbox,
     * and the requests come from whoever holds the password.
     *
     * @param $user \WP_User
     * @return bool
     */
    private function hasReachedCodeRequestLimit($user)
    {
        $minutes = (int)Helper::getSetting('login_try_timing');
        $limit = (int)Helper::getSetting('login_try_limit');

        $limit = (int)apply_filters('fluent_auth/2fa_code_request_limit', $limit, $user);
        $minutes = (int)apply_filters('fluent_auth/2fa_code_request_timing', $minutes, $user);

        if (!$minutes || !$limit) {
            return false;
        }

        $count = flsDb()->table('fls_login_hashes')
            ->where('user_id', $user->ID)
            ->whereIn('use_type', $this->twoFaUseTypes())
            ->where('created_at', '>', date('Y-m-d H:i:s', current_time('timestamp') - $minutes * 60))
            ->count();

        return $count > $limit;
    }

    private function isEnabled($user = false)
    {
        // An account under attack is challenged regardless of the role settings.
        if ($this->isChallengeRequired($user)) {
            return true;
        }

        if (Helper::getSetting('email2fa') !== 'yes') {
            return false;
        }

        if (!$user) {
            return true;
        }

        $roles = Helper::getSetting('email2fa_roles');

        return (bool)array_intersect($roles, array_values($user->roles));
    }


    private function get2FaFormHtml($data = [])
    {
        $redirectTo = Arr::get($data, 'redirect_to');

        if ($redirectTo) {
            $redirectTo = esc_url_raw($redirectTo);
        }

        ob_start();
        ?>
        <form
            style="margin-top: 20px;margin-left: 0;padding: 26px 24px 34px;font-weight: 400;overflow: hidden;background: #fff;border: 1px solid #c3c4c7;box-shadow: 0 1px 3px rgb(0 0 0 / 4%);"
            class="fls_2fs" id="fls_2fa_form">
            <input type="hidden" name="login_hash" value="<?php echo esc_attr(Arr::get($data, 'login_hash')); ?>"/>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirectTo); ?>"/>
            <div class="user-pass-wrap">
                <p style="margin-bottom: 20px;"><?php esc_html_e('Please check your email inbox and get the 2 factor Authentication code and Provide here to login', 'fluent-security'); ?></p>
                <label for="login_passcode"><?php esc_html_e('Two-Factor Authentication Code', 'fluent-security'); ?></label>
                <div class="wp-pwd">
                    <input style="font-size: 14px;" placeholder="<?php esc_html_e('Login Code', 'fluent-security'); ?>"
                           type="number"
                           value="<?php echo (isset($data['auto_code'])) ? esc_attr($data['auto_code']) : ''; ?>"
                           name="login_passcode" id="login_passcode" class="input" size="20"/>
                </div>
                <div>
                    <button
                        style="display: block; cursor: pointer; width: 100%;border: 1px solid #2271b1;background: #2271b1;color: #fff;text-decoration: none;text-shadow: none;min-height: 32px;line-height: 2.30769231;padding: 4px 12px;font-size: 13px;border-radius: 3px;"
                        id="fls_2fa_confirm" type="submit">
                        <?php esc_html_e('Login', 'fluent-security'); ?>
                    </button>
                </div>
            </div>
        </form>
        <?php

        return ob_get_clean();
    }
}
