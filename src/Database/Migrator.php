<?php

namespace WSms\Database;

defined('ABSPATH') || exit;

class Migrator
{
    /**
     * Create all plugin database tables.
     */
    public static function createTables(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $sql = self::getUserFactorsSchema($wpdb->prefix, $charsetCollate)
             . self::getVerificationsSchema($wpdb->prefix, $charsetCollate)
             . self::getAuthLogsSchema($wpdb->prefix, $charsetCollate);

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Drop all plugin database tables.
     */
    public static function dropTables(): void
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'wsms_auth_logs',
            $wpdb->prefix . 'wsms_verifications',
            $wpdb->prefix . 'wsms_user_factors',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL
        }
    }

    private static function getUserFactorsSchema(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}wsms_user_factors (
            id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id         BIGINT UNSIGNED NOT NULL,
            channel_id      VARCHAR(50) NOT NULL,
            status          VARCHAR(20) NOT NULL DEFAULT 'pending',
            identifier      VARCHAR(255) DEFAULT NULL,
            meta            TEXT,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_user_channel (user_id, channel_id),
            INDEX idx_status (status),
            UNIQUE INDEX idx_channel_identifier (channel_id, identifier)
        ) {$charsetCollate};\n";
    }

    private static function getVerificationsSchema(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}wsms_verifications (
            id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id         BIGINT UNSIGNED NOT NULL,
            type            VARCHAR(50) NOT NULL,
            channel_id      VARCHAR(50),
            identifier      VARCHAR(255) NOT NULL,
            code            VARCHAR(255) NOT NULL,
            attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts    TINYINT UNSIGNED NOT NULL DEFAULT 3,
            expires_at      DATETIME NOT NULL,
            used_at         DATETIME,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_type (user_id, type, channel_id),
            INDEX idx_identifier (identifier),
            INDEX idx_expires (expires_at)
        ) {$charsetCollate};\n";
    }

    private static function getAuthLogsSchema(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}wsms_auth_logs (
            id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id         BIGINT UNSIGNED,
            event           VARCHAR(50) NOT NULL,
            channel_id      VARCHAR(50),
            status          VARCHAR(20) NOT NULL,
            ip_address      VARCHAR(45),
            user_agent      VARCHAR(500),
            meta            TEXT,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_event (event),
            INDEX idx_created (created_at),
            INDEX idx_user_event (user_id, event)
        ) {$charsetCollate};\n";
    }
}
