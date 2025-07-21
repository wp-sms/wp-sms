<?php

namespace WP_SMS\Services\Database\Managers;

use WP_SMS\Install;
use WP_SMS\Utils\OptionUtil as Option;
use WP_SMS\Services\Database\DatabaseFactory;
use WP_SMS\Services\Database\Schema\Manager;

/**
 * Handles database table operations, including creation, inspection,
 * and deletion of tables.
 *
 * This class provides methods to create all tables, create individual tables,
 * drop individual tables, and drop all tables as required for managing
 * the database schema in WP SMS.
 */
class TableHandler
{
    /**
     * Create all database tables and add missing columns if they don't exist.
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
                    self::createTable($tableName, $schema);
                } else {
                    $schema = Manager::getSchemaForTable($tableName);
                    self::addMissingColumns($tableName, $schema);
                }
            } catch (\Exception $e) {
                throw new \RuntimeException("Failed to inspect or create table `$tableName`: " . $e->getMessage(), 0, $e);
            }
        }

        Option::saveOptionGroup('check', false, 'db');

        if (Install::isFresh()) {
            Option::saveOptionGroup('migrated', true, 'db');
            Option::saveOptionGroup('manual_migration_tasks', [], 'db');
            Option::saveOptionGroup('auto_migration_tasks', [], 'db');
            Option::saveOptionGroup('version', WP_SMS_VERSION, 'db');
            Option::saveOptionGroup('is_done', true, 'ajax_background_process');
            return;
        }

        Option::saveOptionGroup('migrated', false, 'db');
        Option::saveOptionGroup('migration_status_detail', null, 'db');
        Option::saveOptionGroup('is_done', null, 'ajax_background_process');
        Option::saveOptionGroup('status', null, 'ajax_background_process');

        $dismissedNotices = get_option('wp_sms_dismissed_notices', []);

        if (in_array('database_manual_migration_progress', $dismissedNotices, true)) {
            $dismissedNotices = array_diff($dismissedNotices, ['database_manual_migration_progress']);

            update_option('wp_sms_dismissed_notices', $dismissedNotices);
        }
    }

    /**
     * Add missing columns to an existing table.
     *
     * @param string $tableName The name of the table
     * @param array $schema The expected schema including columns
     * @return void
     * @throws \RuntimeException If column addition fails
     */
    public static function addMissingColumns(string $tableName, array $schema)
    {
        global $wpdb;

        if (!isset($schema['columns'])) {
            return;
        }
        $prefixedTableName   = $wpdb->prefix . 'sms_' . $tableName;
        $existingColumns     = $wpdb->get_results("SHOW COLUMNS FROM `{$prefixedTableName}`", ARRAY_A);
        $existingColumnNames = array_column($existingColumns, 'Field');

        foreach ($schema['columns'] as $columnName => $definition) {
            $existingColumn = array_filter($existingColumns, function ($col) use ($columnName) {
                return $col['Field'] === $columnName;
            });

            if (!$existingColumn) {
                $wpdb->query("ALTER TABLE `{$prefixedTableName}` ADD COLUMN `{$columnName}` {$definition}");
            } else {
                $actualType   = strtoupper($existingColumn[array_key_first($existingColumn)]['Type']);
                $expectedType = strtoupper($definition);

                if ($actualType !== $expectedType) {
                    $wpdb->query("ALTER TABLE `{$prefixedTableName}` MODIFY COLUMN `{$columnName}` {$definition}");
                }
            }
        }
    }

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
}
