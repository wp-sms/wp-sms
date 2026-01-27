<?php

namespace WP_SMS\Services\Database;

/**
 * Helper methods that are used by the database service.
 *
 * @package   Database
 * @version   1.0.0
 * @since     7.1
 * @author    Hooman
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

        $base = !empty($pagenow) ? self_admin_url($pagenow) : self_admin_url('index.php');

        return rawurlencode(add_query_arg(wp_unslash($_GET), $base));
    }

    /**
     * Check if a table exists in the database.
     *
     * @param string $tableName The full table name including prefix.
     * @return bool True if the table exists, false otherwise.
     */
    public static function tableExists($tableName)
    {
        global $wpdb;

        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $tableName);
        return $wpdb->get_var($query) === $tableName;
    }

    /**
     * Get the full table name with WordPress prefix.
     *
     * @param string $tableName The table name without prefix.
     * @return string The full table name with prefix.
     */
    public static function getFullTableName($tableName)
    {
        global $wpdb;

        return $wpdb->prefix . 'sms_' . $tableName;
    }
}
