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
                'group_ID'      => 'BIGINT(20)',
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
                'media'     => 'TEXT',
                'response'  => 'TEXT NOT NULL',
                'status'    => 'VARCHAR(10) NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (ID)'
            ],
        ],
        'otp'              => [
            'columns'     => [
                'ID'           => 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
                'phone_number' => 'VARCHAR(20) NOT NULL',
                'agent'        => 'VARCHAR(255) NOT NULL',
                'code'         => 'CHAR(32) NOT NULL',
                'created_at'   => 'INT(10) UNSIGNED NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (ID)'
            ],
        ],
        'otp_attempts'     => [
            'columns'     => [
                'ID'           => 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
                'phone_number' => 'VARCHAR(20) NOT NULL',
                'agent'        => 'VARCHAR(255) NOT NULL',
                'code'         => 'VARCHAR(255) NOT NULL',
                'result'       => 'TINYINT(1) NOT NULL',
                'time'         => 'INT(10) UNSIGNED NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (ID)',
                'KEY (phone_number)'
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
