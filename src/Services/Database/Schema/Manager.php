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