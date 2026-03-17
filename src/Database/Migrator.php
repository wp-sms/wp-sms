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
             . self::getAuthLogsSchema($wpdb->prefix, $charsetCollate)
             . self::getFlowsSchema($wpdb->prefix, $charsetCollate)
             . self::getFlowExecutionsSchema($wpdb->prefix, $charsetCollate)
             . self::getMessageLogsSchema($wpdb->prefix, $charsetCollate)
             . self::getContactsSchema($wpdb->prefix, $charsetCollate)
             . self::getTagsSchema($wpdb->prefix, $charsetCollate)
             . self::getContactTagSchema($wpdb->prefix, $charsetCollate)
             . self::getListsSchema($wpdb->prefix, $charsetCollate);

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
            $wpdb->prefix . 'wsms_contact_tag',
            $wpdb->prefix . 'wsms_lists',
            $wpdb->prefix . 'wsms_tags',
            $wpdb->prefix . 'wsms_contacts',
            $wpdb->prefix . 'wsms_message_logs',
            $wpdb->prefix . 'wsms_flow_executions',
            $wpdb->prefix . 'wsms_flows',
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
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT UNSIGNED NOT NULL,
            channel_id      VARCHAR(50) NOT NULL,
            status          VARCHAR(20) NOT NULL DEFAULT 'pending',
            identifier      VARCHAR(255) DEFAULT NULL,
            meta            TEXT,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            INDEX idx_user_id (user_id),
            INDEX idx_user_channel (user_id, channel_id),
            INDEX idx_status (status),
            UNIQUE INDEX idx_channel_identifier (channel_id, identifier)
        ) {$charsetCollate};\n";
    }

    private static function getVerificationsSchema(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}wsms_verifications (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT UNSIGNED DEFAULT NULL,
            session_id      VARCHAR(64) DEFAULT NULL,
            type            VARCHAR(50) NOT NULL,
            channel_id      VARCHAR(50),
            identifier      VARCHAR(255) NOT NULL,
            code            VARCHAR(255) NOT NULL,
            attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts    TINYINT UNSIGNED NOT NULL DEFAULT 3,
            expires_at      DATETIME NOT NULL,
            used_at         DATETIME,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            INDEX idx_user_type (user_id, type, channel_id),
            INDEX idx_session_type (session_id, type),
            INDEX idx_identifier (identifier),
            INDEX idx_expires (expires_at)
        ) {$charsetCollate};\n";
    }

    private static function getAuthLogsSchema(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}wsms_auth_logs (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT UNSIGNED,
            event           VARCHAR(50) NOT NULL,
            channel_id      VARCHAR(50),
            status          VARCHAR(20) NOT NULL,
            ip_address      VARCHAR(45),
            user_agent      VARCHAR(500),
            meta            TEXT,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            INDEX idx_user_id (user_id),
            INDEX idx_event (event),
            INDEX idx_created (created_at),
            INDEX idx_user_event (user_id, event)
        ) {$charsetCollate};\n";
    }

    private static function getFlowsSchema(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}wsms_flows (
            id              CHAR(26) NOT NULL,
            name            VARCHAR(255) NOT NULL,
            description     TEXT,
            trigger_type    VARCHAR(100) NOT NULL,
            trigger_config  TEXT,
            steps           LONGTEXT NOT NULL,
            status          VARCHAR(20) DEFAULT 'draft',
            published_steps LONGTEXT,
            published_at    DATETIME,
            priority        SMALLINT DEFAULT 0,
            created_by      BIGINT,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_trigger_status (trigger_type, status),
            INDEX idx_status (status)
        ) {$charsetCollate};\n";
    }

    private static function getFlowExecutionsSchema(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}wsms_flow_executions (
            id                  CHAR(26) NOT NULL,
            flow_id             CHAR(26) NOT NULL,
            trigger_data        LONGTEXT,
            status              VARCHAR(20) NOT NULL,
            step_logs           LONGTEXT,
            wait_event_type     VARCHAR(100),
            wait_match_expr     VARCHAR(500),
            wait_node_id        VARCHAR(100),
            wait_payload        LONGTEXT,
            wait_timeout_at     DATETIME,
            wait_timeout_action VARCHAR(20),
            error               TEXT,
            started_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at        DATETIME,
            PRIMARY KEY (id),
            INDEX idx_flow_id (flow_id),
            INDEX idx_status (status),
            INDEX idx_wait (status, wait_event_type),
            INDEX idx_wait_timeout (status, wait_timeout_at),
            INDEX idx_started (started_at)
        ) {$charsetCollate};\n";
    }

    private static function getMessageLogsSchema(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}wsms_message_logs (
            id              CHAR(26) NOT NULL,
            execution_id    CHAR(26),
            gateway_id      VARCHAR(50) NOT NULL,
            channel         VARCHAR(20) NOT NULL,
            type            VARCHAR(20) DEFAULT 'transactional',
            recipient       VARCHAR(255) NOT NULL,
            subject         VARCHAR(500),
            body_preview    VARCHAR(500),
            status          VARCHAR(20) NOT NULL,
            provider_id     VARCHAR(255),
            error           TEXT,
            cost            DECIMAL(10,6),
            sent_at         DATETIME,
            delivered_at    DATETIME,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_execution (execution_id),
            INDEX idx_channel_status (channel, status),
            INDEX idx_recipient (recipient),
            INDEX idx_created (created_at),
            INDEX idx_gateway_provider (gateway_id, provider_id)
        ) {$charsetCollate};\n";
    }

    private static function getContactsSchema(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}wsms_contacts (
            id              CHAR(26) NOT NULL,
            email           VARCHAR(255),
            phone           VARCHAR(50),
            first_name      VARCHAR(100),
            last_name       VARCHAR(100),
            wp_user_id      BIGINT,
            status          VARCHAR(20) DEFAULT 'subscribed',
            custom_fields   JSON,
            source          VARCHAR(50),
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE INDEX idx_email (email),
            INDEX idx_phone (phone),
            INDEX idx_wp_user (wp_user_id),
            INDEX idx_status (status)
        ) {$charsetCollate};\n";
    }

    private static function getTagsSchema(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}wsms_tags (
            id      CHAR(26) NOT NULL,
            name    VARCHAR(100) NOT NULL,
            slug    VARCHAR(100) NOT NULL,
            color   VARCHAR(7),
            PRIMARY KEY (id),
            UNIQUE INDEX idx_slug (slug)
        ) {$charsetCollate};\n";
    }

    private static function getContactTagSchema(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}wsms_contact_tag (
            contact_id  CHAR(26) NOT NULL,
            tag_id      CHAR(26) NOT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (contact_id, tag_id),
            INDEX idx_tag (tag_id)
        ) {$charsetCollate};\n";
    }

    private static function getListsSchema(string $prefix, string $charsetCollate): string
    {
        return "CREATE TABLE {$prefix}wsms_lists (
            id              CHAR(26) NOT NULL,
            name            VARCHAR(255) NOT NULL,
            type            VARCHAR(20) NOT NULL,
            conditions      JSON,
            tag_id          CHAR(26),
            description     TEXT,
            contact_count   INT DEFAULT 0,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_type (type)
        ) {$charsetCollate};\n";
    }
}
