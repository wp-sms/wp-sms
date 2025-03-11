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
        'subscribes' => [
            'columns' => [
                'id'             => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
                'date'           => 'DATETIME',
                'name'           => 'VARCHAR(250)',
                'mobile'         => 'VARCHAR(20) NOT NULL',
                'status'         => 'TINYINT(1)',
                'activate_key'   => 'INT(11)',
                'custom_fields'  => 'TEXT NULL',
                'group_ID'       => 'INT(5)',
            ],
            'constraints' => [
                'PRIMARY KEY (id)'
            ],
        ],
        'subscribes_group' => [
            'columns' => [
                'id'         => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
                'name'       => 'VARCHAR(250)',
            ],
            'constraints' => [
                'PRIMARY KEY (id)'
            ],
        ],
        'send' => [
            'columns' => [
                'id'         => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
                'date'       => 'DATETIME',
                'sender'     => 'VARCHAR(20) NOT NULL',
                'message'    => 'TEXT NOT NULL',
                'recipient'  => 'TEXT NOT NULL',
                'response'   => 'TEXT NOT NULL',
                'status'     => "VARCHAR(10) NOT NULL",
            ],
            'constraints' => [
                'PRIMARY KEY (id)'
            ],
        ],
        'numbers' => [
            'columns' => [
                'id'              => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
                'number'          => 'VARCHAR(20) NOT NULL UNIQUE',
                'country_code'    => 'VARCHAR(10) NOT NULL',
                'first_name'      => 'VARCHAR(50)',
                'last_name'       => 'VARCHAR(50)',
                'display_name'    => 'VARCHAR(100)',
                'user_id'         => 'BIGINT(20) NOT NULL',
                'status'          => "ENUM('active', 'pending', 'deactivated') NOT NULL DEFAULT 'pending'",
                'unsubscribed'    => 'BOOLEAN NOT NULL DEFAULT FALSE',
                'verified'        => 'BOOLEAN NOT NULL DEFAULT FALSE',
                'source'          => 'VARCHAR(255)',
                'meta'            => 'TEXT',
                'secondary_number' => 'VARCHAR(20) DEFAULT NULL',
                'last_sent_at'    => 'DATETIME DEFAULT NULL',
                'success_count'   => 'INT(11) NOT NULL DEFAULT 0',
                'fail_count'      => 'INT(11) NOT NULL DEFAULT 0',
                'opt_in_date'     => 'DATETIME DEFAULT NULL',
                'opt_out_at'      => 'DATETIME DEFAULT NULL',
                'created_at'      => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'updated_at'      => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            ],
            'constraints' => [
                'PRIMARY KEY (id)',
                'KEY user_id (user_id)'
            ],
        ]
    ];

    /**
     * Retrieve the fully defined schema (columns and constraints) for a specific table.
     *
     * @param string $tableName The name of the table.
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
}
