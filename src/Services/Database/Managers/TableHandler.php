<?php

namespace WP_SMS\Services\Database\Managers;

use WP_SMS\Core\CoreFactory;
use WP_SMS\Option;
use WP_SMS\Services\Database\DatabaseFactory;
use WP_SMS\Services\Database\Schema\Manager;

/**
 * Handles database table operations, including creation, inspection,
 * and deletion of tables.
 *
 * This class provides methods to create all tables, create individual tables,
 * drop individual tables, and drop all tables as required for managing
 * the database schema in WP SMS.
 *
 * @package   Database
 * @version   1.0.0
 * @since     7.1
 * @author    Hooman
 */
class TableHandler
{
    /**
     * Create all database tables if they do not already exist.
     *
     * This method iterates through all known table names, inspects each table,
     * and creates it if it is missing using the predefined schema.
     *
     * @return void
     * @throws \RuntimeException If a table creation or inspection fails.
     */
    public static function createAllTables()
    {
        $tableNames = Manager::getAllTableNames();

        foreach ($tableNames as $tableName) {
            try {
                $inspect = DatabaseFactory::table('inspect')
                    ->setName($tableName)
                    ->execute();

                if (!$inspect->getResult()) {
                    $schema = Manager::getSchemaForTable($tableName);

                    DatabaseFactory::table('create')
                        ->setName($tableName)
                        ->setArgs($schema)
                        ->execute();
                }
            } catch (\Exception $e) {
                throw new \RuntimeException("Failed to inspect or create table `$tableName`: " . $e->getMessage(), 0, $e);
            }
        }

        Option::updateInGroup('schema_check', '', Option::DB_GROUP);

        if (CoreFactory::isFresh()) {
            Option::updateInGroup('migrated', true, Option::DB_GROUP);
            Option::updateInGroup('version', WP_SMS_VERSION, Option::DB_GROUP);
            return;
        }

        // For upgrades, mark migration as pending
        Option::updateInGroup('migrated', false, Option::DB_GROUP);
        Option::deleteFromGroup('migration_status_detail', Option::DB_GROUP);
    }

    /**
     * Create a single table.
     *
     * @param string $tableName The name of the table to create.
     * @param array $schema The schema for the table.
     * @return void
     * @throws \RuntimeException If the table creation fails.
     */
    public static function createTable(string $tableName, array $schema)
    {
        try {
            $createOperation = DatabaseFactory::table('create');
            $createOperation
                ->setName($tableName)
                ->setArgs($schema)
                ->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to create table `$tableName`: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Drop a single table.
     *
     * @param string $tableName The name of the table to drop.
     * @return void
     * @throws \RuntimeException If the table drop operation fails.
     */
    public static function dropTable(string $tableName)
    {
        try {
            DatabaseFactory::table('drop')
                ->setName($tableName)
                ->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to drop table `$tableName`: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Drop all known tables.
     *
     * @return void
     * @throws \RuntimeException If any table drop operation fails.
     */
    public static function dropAllTables()
    {
        $tableNames = Manager::getAllTableNames();

        foreach ($tableNames as $tableName) {
            try {
                self::dropTable($tableName);
            } catch (\Exception $e) {
                throw new \RuntimeException("Failed to drop table `$tableName`: " . $e->getMessage(), 0, $e);
            }
        }
    }

    /**
     * Check if a specific table exists.
     *
     * @param string $tableName The name of the table to check.
     * @return bool True if the table exists, false otherwise.
     */
    public static function tableExists(string $tableName)
    {
        try {
            $inspect = DatabaseFactory::table('inspect')
                ->setName($tableName)
                ->execute();

            return $inspect->getResult();
        } catch (\Exception $e) {
            return false;
        }
    }
}
