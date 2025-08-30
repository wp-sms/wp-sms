<?php

namespace WP_SMS\Services\Database\Schema;

/**
 * Manages database table schemas.
 *
 * This class provides methods to retrieve schemas and manage table names
 * for database operations.
 */
class Manager
{
    /**
     * The schema definitions for database tables.
     *
     * @var array
     */
    private static $tablesSchema = [
        'subscribes'       => [
            'columns'     => [
                'ID'            => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
                'date'          => 'DATETIME',
                'name'          => 'VARCHAR(250)',
                'mobile'        => 'VARCHAR(20) NOT NULL',
                'status'        => 'TINYINT(1)',
                'activate_key'  => 'INT(11)',
                'custom_fields' => 'TEXT NULL',
                'group_ID'      => 'INT(5)',
            ],
            'constraints' => [
                'PRIMARY KEY (ID)'
            ],
        ],
        'subscribes_group' => [
            'columns'     => [
                'ID'   => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
                'name' => 'VARCHAR(250)',
            ],
            'constraints' => [
                'PRIMARY KEY (ID)'
            ],
        ],
        'send'             => [
            'columns'     => [
                'ID'        => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
                'date'      => 'DATETIME',
                'sender'    => 'VARCHAR(20) NOT NULL',
                'message'   => 'TEXT NOT NULL',
                'recipient' => 'TEXT NOT NULL',
                'response'  => 'TEXT NOT NULL',
                'status'    => "VARCHAR(10) NOT NULL",
            ],
            'constraints' => [
                'PRIMARY KEY (ID)'
            ],
        ],
        'numbers'          => [
            'columns'     => [
                'id'               => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
                'number'           => 'VARCHAR(20) NOT NULL UNIQUE',
                'country_code'     => 'VARCHAR(10) NOT NULL',
                'first_name'       => 'VARCHAR(50)',
                'last_name'        => 'VARCHAR(50)',
                'display_name'     => 'VARCHAR(100)',
                'user_id'          => 'BIGINT(20) NOT NULL',
                'status'           => "ENUM('active', 'pending', 'deactivated') NOT NULL DEFAULT 'pending'",
                'unsubscribed'     => 'BOOLEAN NOT NULL DEFAULT FALSE',
                'verified'         => 'BOOLEAN NOT NULL DEFAULT FALSE',
                'source'           => 'VARCHAR(255)',
                'meta'             => 'TEXT',
                'secondary_number' => 'VARCHAR(20) DEFAULT NULL',
                'last_sent_at'     => 'DATETIME DEFAULT NULL',
                'success_count'    => 'INT(11) NOT NULL DEFAULT 0',
                'fail_count'       => 'INT(11) NOT NULL DEFAULT 0',
                'opt_in_date'      => 'DATETIME DEFAULT NULL',
                'opt_out_at'       => 'DATETIME DEFAULT NULL',
                'created_at'       => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'updated_at'       => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            ],
            'constraints' => [
                'PRIMARY KEY (id)',
                'KEY user_id (user_id)'
            ],
        ],
        // Added tables for MFA and Auth Event logging
        'identifiers' => [
            'columns' => [
                'id'           => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                'user_id'      => 'BIGINT UNSIGNED NOT NULL',
                "factor_type" => "ENUM('phone','email','totp','webauthn','backup') NOT NULL",
                'factor_value' => 'VARBINARY(255) NOT NULL',
                'value_hash'   => 'CHAR(64) NOT NULL',
                'verified'     => 'BOOLEAN NOT NULL DEFAULT FALSE',
                'created_at'   => 'DATETIME NOT NULL',
                'verified_at'  => 'DATETIME NULL',
                'last_used_at' => 'DATETIME NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (id)',
                'UNIQUE KEY unique_factor_type_value_hash (factor_type, value_hash)',
                'KEY idx_mfa_user (user_id)',
                'KEY idx_mfa_value_hash (value_hash)',
                'KEY idx_mfa_user_type (user_id, factor_type)'
            ],
        ],
        'auth_events' => [
            'columns' => [
                'id'              => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                'event_id'        => 'CHAR(36) NOT NULL',
                'flow_id'         => 'CHAR(36) NOT NULL',
                'timestamp_utc'   => 'TIMESTAMP NOT NULL',
                'user_id'         => 'BIGINT NULL',
                'channel'         => 'VARCHAR(64) NOT NULL',
                'event_type'      => 'VARCHAR(64) NOT NULL',
                'result'          => 'VARCHAR(32) NOT NULL',
                'client_ip_masked' => 'VARCHAR(64) NULL',
                'geo_country'     => 'CHAR(2) NULL',
                'wp_role'         => 'VARCHAR(32) NULL',
                'vendor_sid'      => 'VARCHAR(64) NULL',
                'vendor_status'   => 'VARCHAR(32) NULL',
                'factor_id'       => 'CHAR(36) NULL',
                'attempt_count'   => 'SMALLINT NULL',
                'retention_days'  => 'SMALLINT NOT NULL DEFAULT 30',
                'user_agent'      => 'TEXT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (id)',
                'UNIQUE KEY unique_event_id (event_id)',
                'KEY idx_auth_flow (flow_id)',
                'KEY idx_auth_user_ts (user_id, timestamp_utc DESC)',
                'KEY idx_auth_factor (factor_id)'
            ],
        ],
        'otp_sessions' => [
            'columns' => [
                'id'           => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                'flow_id' => 'CHAR(36) NOT NULL',
                'phone'      => 'VARCHAR(20) NULL',
                'email'      => 'VARCHAR(255) NULL',
                'otp_hash'   => 'CHAR(64) NOT NULL',
                'expires_at' => 'DATETIME NOT NULL',
                'attempt_count' => 'INT NOT NULL DEFAULT 0',
                'channel'    => 'VARCHAR(32) NOT NULL DEFAULT "sms"',
                'created_at' => 'DATETIME NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (id)',
                'UNIQUE KEY unique_flow_id (flow_id)',
                'KEY idx_otp_phone (phone)',
                'KEY idx_otp_email (email)',
                'KEY idx_otp_expires (expires_at)'
            ],
        ],
        'magic_links' => [
            'columns' => [
                'id'          => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                'flow_id'     => 'CHAR(36) NOT NULL',
                'token_hash'  => 'CHAR(64) NOT NULL',
                'expires_at'  => 'DATETIME NOT NULL',
                'used_at'     => 'DATETIME NULL',
                'created_at'  => 'DATETIME NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (id)',
                'UNIQUE KEY unique_flow_id (flow_id)',
                'KEY idx_magic_expires (expires_at)'
            ],
        ],

    ];

    /**
     * Retrieve the fully defined schema (columns and constraints) for a specific table.
     *
     * @param string $tableName The name of the table.
     * @return array|null The schema for the table or null if not found.
     */
    public static function getSchemaForTable(string $tableName)
    {
        return isset(self::$tablesSchema[$tableName]) ? self::$tablesSchema[$tableName] : null;
    }

    /**
     * Retrieve all table names.
     *
     * @return array An array of all table names defined in the schema.
     */
    public static function getAllTableNames()
    {
        return array_keys(self::$tablesSchema);
    }
}
