<?php

namespace FluentAuth\App\Http\Controllers;

use FluentAuth\App\Helpers\Helper;
use FluentAuth\App\Services\TwoFa\TotpTwoFaMethod;

/**
 * Who has a second factor, and the way back in when a device is lost.
 *
 * Without this the only way to answer either question is to open every user's profile
 * one at a time, which for the one case that matters - somebody locked out, on the
 * phone, right now - is not an answer at all.
 */
class TwoFaController
{
    const PER_PAGE = 20;

    public static function getUsers(\WP_REST_Request $request)
    {
        $page = max(1, (int)$request->get_param('page'));
        $search = sanitize_text_field((string)$request->get_param('search'));
        $filter = sanitize_text_field((string)$request->get_param('filter'));

        $args = [
            'number'  => self::PER_PAGE,
            'paged'   => $page,
            'orderby' => 'ID',
            'order'   => 'ASC',
            'fields'  => 'all'
        ];

        if ($search) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        /*
         * Enrollment is a meta key, so filtering on it belongs in the query rather than
         * in a loop over the page - otherwise "show me who is enrolled" returns however
         * many of the first twenty users happen to be.
         */
        if ($filter === 'enrolled') {
            $args['meta_query'] = [
                [
                    'key'     => TotpTwoFaMethod::META_SECRET,
                    'compare' => 'EXISTS'
                ]
            ];
        } elseif ($filter === 'not_enrolled') {
            $args['meta_query'] = [
                [
                    'key'     => TotpTwoFaMethod::META_SECRET,
                    'compare' => 'NOT EXISTS'
                ]
            ];
        }

        $query = new \WP_User_Query($args);

        $users = [];

        foreach ($query->get_results() as $user) {
            $users[] = self::formatUser($user);
        }

        return [
            'users' => [
                'data'         => $users,
                'total'        => (int)$query->get_total(),
                'per_page'     => self::PER_PAGE,
                'current_page' => $page,
                'last_page'    => (int)ceil($query->get_total() / self::PER_PAGE)
            ],
            'summary' => self::getSummary()
        ];
    }

    /**
     * Turns off a user's authenticator app.
     *
     * This is the lost phone path, so it is the one action here that changes anything -
     * and it lowers the account's protection, which is why it re-checks the capability
     * to edit that specific user rather than trusting the endpoint's own permission.
     */
    public static function resetUser(\WP_REST_Request $request)
    {
        $userId = (int)$request->get_param('id');

        $user = $userId ? get_user_by('ID', $userId) : false;

        if (!$user) {
            return new \WP_Error('not_found', __('The user could not be found', 'fluent-security'), ['status' => 404]);
        }

        if (!current_user_can('edit_user', $userId)) {
            return new \WP_Error(
                'forbidden',
                __('You are not allowed to change this user', 'fluent-security'),
                ['status' => 403]
            );
        }

        if (!TotpTwoFaMethod::isEnrolled($user)) {
            return new \WP_Error(
                'not_enrolled',
                __('This user does not have an authenticator app set up', 'fluent-security'),
                ['status' => 422]
            );
        }

        TotpTwoFaMethod::disable($user);

        return [
            'user'    => self::formatUser($user),
            'summary' => self::getSummary(),
            /* translators: %s: the user's login name */
            'message' => sprintf(__('The authenticator app for %s has been turned off. They can set up a new one from their profile.', 'fluent-security'), $user->user_login)
        ];
    }

    /**
     * @param $user \WP_User
     * @return array
     */
    private static function formatUser($user)
    {
        $enrolled = TotpTwoFaMethod::isEnrolled($user);

        $emailRoles = Helper::getSetting('email2fa_roles');

        $emailApplies = Helper::getSetting('email2fa') === 'yes'
            && is_array($emailRoles)
            && (bool)array_intersect($emailRoles, array_values($user->roles));

        return [
            'id'              => (int)$user->ID,
            'user_login'      => $user->user_login,
            'display_name'    => $user->display_name,
            'user_email'      => $user->user_email,
            'roles'           => array_values($user->roles),
            'totp_enrolled'   => $enrolled,
            'totp_allowed'    => TotpTwoFaMethod::isAllowedForUser($user),
            'totp_required'   => TotpTwoFaMethod::isRequiredForUser($user),
            'activated_at'    => $enrolled ? get_user_meta($user->ID, TotpTwoFaMethod::META_ACTIVATED_AT, true) : '',
            'recovery_codes'  => $enrolled ? TotpTwoFaMethod::getRemainingRecoveryCount($user) : 0,
            'recovery_total'  => TotpTwoFaMethod::RECOVERY_CODE_COUNT,
            'email_2fa'       => $emailApplies,
            'can_edit'        => current_user_can('edit_user', $user->ID)
        ];
    }

    /**
     * Counted with queries rather than by walking every user, so a site with a large
     * membership does not pay for this panel.
     *
     * @return array
     */
    private static function getSummary()
    {
        $enrolled = new \WP_User_Query([
            'number'     => 1,
            'fields'     => 'ID',
            'meta_query' => [
                [
                    'key'     => TotpTwoFaMethod::META_SECRET,
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        $all = new \WP_User_Query([
            'number' => 1,
            'fields' => 'ID'
        ]);

        return [
            'enrolled'    => (int)$enrolled->get_total(),
            'total_users' => (int)$all->get_total()
        ];
    }
}
