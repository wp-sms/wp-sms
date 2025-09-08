<?php

namespace WP_SMS\Service\Database;

/**
 * Helper methods that are used by the database service.
 * 
 * @package WP_SMS\Service\Database
 */
class DatabaseHelper
{
    /**
     * Get the absolute URL of the current admin (Dashboard) screen.
     *
     * @return string Absolute admin URL for the current screen or empty string.
     */
    public static function getCurrentAdminUrl()
    {
        global $pagenow;

        $base = ! empty($pagenow) ? self_admin_url($pagenow) : self_admin_url('index.php');

        return rawurlencode(add_query_arg(wp_unslash($_GET), $base));
    }
}