<?php

namespace FluentAuth\App\Http\Controllers;

use FluentAuth\App\Helpers\Helper;

class LogsController
{
    public static function getLogs(\WP_REST_Request $request)
    {
        $orderByColumn = sanitize_sql_orderby($request->get_param('sortBy')) ?: 'id';
        $orderBy = sanitize_sql_orderby($request->get_param('sortType')) ?: 'DESC';

        $query = flsDb()->table('fls_auth_logs')->orderBy($orderByColumn, $orderBy);

        if ($statuses = $request->get_param('statuses')) {
            $statuses = array_filter(map_deep($statuses, 'sanitize_text_field'));
            if ($statuses && !in_array('all', $statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        /*
         * The address is searched as well as the name. Following one attacker across a log
         * is the most common reason to search it at all, and until now the only way to do
         * that was to read every page looking for the same number.
         */
        if ($search = $request->get_param('search')) {
            $search = sanitize_text_field($search);
            $query->where(function ($q) use ($search) {
                $q->where('username', 'LIKE', '%' . $search . '%');
                $q->orWhere('ip', 'LIKE', '%' . $search . '%');
                $q->orWhere('media', 'LIKE', '%' . $search . '%');
                return $q;
            });
        }

        $logs = $query->paginate();

        $wpTimestamp = current_time('timestamp');
        $dateFormat = get_option('date_format') . ' ' . get_option('time_format');

        foreach ($logs['data'] as $log) {
            $timestamp = strtotime($log->created_at);

            /* translators: %s: a human readable time difference, e.g. "5 mins" */
            $log->human_time_diff = sprintf(
                __('%s ago', 'fluent-security'),
                human_time_diff($timestamp, $wpTimestamp)
            );

            // The exact moment, in the format and language the site is set to.
            $log->created_at_human = date_i18n($dateFormat, $timestamp);

            $log->media_label = Helper::getLoginMediaLabel($log->media);
        }

        return [
            'logs' => $logs,
            /*
             * How long these rows last. The screen says so because the log deletes itself
             * on a schedule, and a gap where last month used to be otherwise reads as
             * something having gone wrong rather than as the setting doing its job.
             *
             * Sent with the rows rather than read from the settings the admin screen was
             * booted with, so changing the number and coming back shows the new one.
             */
            'retention' => (int)Helper::getSetting('auto_delete_logs_day')
        ];
    }

    public static function deleteLog(\WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');
        flsDb()->table('fls_auth_logs')->where('id', $id)->delete();

        return [
            'message' => __('Log has been deleted', 'fluent-security')
        ];
    }

    public static function deleteAllLog(\WP_REST_Request $request)
    {
        global $wpdb;

        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}fls_auth_logs");

        return [
            'message' => __('All Logs has been deleted', 'fluent-security')
        ];

    }
}
