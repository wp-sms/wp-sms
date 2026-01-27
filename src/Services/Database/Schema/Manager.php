<?php

namespace WP_SMS\Services\Database\Schema;

/**
 * Manages database table schemas for WP SMS.
 *
 * This class provides methods to retrieve schemas and manage table names
 * for database operations. It defines the canonical structure for all
 * WP SMS database tables.
 *
 * @package   Database
 * @version   1.0.0
 * @since     7.1
 * @author    Hooman
 */
class Manager
{
    /**
     * The schema definitions for database tables.
     *
     * Each table schema includes:
     * - columns: Array of column definitions (column_name => SQL definition)
     * - constraints: Array of constraints and indexes
     *
     * IMPORTANT: These schemas must match the original legacy table definitions
     * in class-wpsms-install.php to ensure compatibility.
     *
     * @var array
     */
    private static $tablesSchema = [
        'subscribes' => [
            'columns' => [
                'ID'            => 'int(10) NOT NULL AUTO_INCREMENT',
                'date'          => 'DATETIME',
                'name'          => 'VARCHAR(250)',
                'mobile'        => 'VARCHAR(20) NOT NULL',
                'status'        => 'tinyint(1)',
                'activate_key'  => 'INT(11)',
                'custom_fields' => 'TEXT NULL',
                'group_ID'      => 'int(5)',
            ],
            'constraints' => [
                'PRIMARY KEY (ID)',
            ],
        ],
        'subscribes_group' => [
            'columns' => [
                'ID'   => 'int(10) NOT NULL AUTO_INCREMENT',
                'name' => 'VARCHAR(250)',
            ],
            'constraints' => [
                'PRIMARY KEY (ID)',
            ],
        ],
        'send' => [
            'columns' => [
                'ID'        => 'int(10) NOT NULL AUTO_INCREMENT',
                'date'      => 'DATETIME',
                'sender'    => 'VARCHAR(20) NOT NULL',
                'message'   => 'TEXT NOT NULL',
                'recipient' => 'TEXT NOT NULL',
                'media'     => 'TEXT NULL',
                'response'  => 'TEXT NOT NULL',
                'status'    => 'varchar(10) NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (ID)',
            ],
        ],
        'otp' => [
            'columns' => [
                'ID'           => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                'phone_number' => 'VARCHAR(20) NOT NULL',
                'agent'        => 'VARCHAR(255) NOT NULL',
                'code'         => 'CHAR(32) NOT NULL',
                'created_at'   => 'INT UNSIGNED NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (ID)',
            ],
        ],
        'otp_attempts' => [
            'columns' => [
                'ID'           => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
                'phone_number' => 'VARCHAR(20) NOT NULL',
                'agent'        => 'VARCHAR(255) NOT NULL',
                'code'         => 'VARCHAR(255) NOT NULL',
                'result'       => 'TINYINT(1) NOT NULL',
                'time'         => 'INT UNSIGNED NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (ID)',
                'KEY phone_number (phone_number)',
            ],
        ],
    ];

    /**
     * Retrieve the fully defined schema (columns and constraints) for a specific table.
     *
     * @param string $tableName The name of the table (without prefix).
     * @return array|null The schema for the table or null if not found.
     */
    public static function getSchemaForTable(string $tableName)
    {
        return self::$tablesSchema[$tableName] ?? null;
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

    /**
     * Get column definitions for a specific table.
     *
     * @param string $tableName The name of the table.
     * @return array|null Array of column definitions or null if table not found.
     */
    public static function getColumnsForTable(string $tableName)
    {
        $schema = self::getSchemaForTable($tableName);
        return $schema['columns'] ?? null;
    }

    /**
     * Get constraints for a specific table.
     *
     * @param string $tableName The name of the table.
     * @return array|null Array of constraints or null if table not found.
     */
    public static function getConstraintsForTable(string $tableName)
    {
        $schema = self::getSchemaForTable($tableName);
        return $schema['constraints'] ?? null;
    }

    /**
     * Check if a table is defined in the schema.
     *
     * @param string $tableName The name of the table.
     * @return bool True if table exists in schema, false otherwise.
     */
    public static function hasTable(string $tableName)
    {
        return isset(self::$tablesSchema[$tableName]);
    }
}
