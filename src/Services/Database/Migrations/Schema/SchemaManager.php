<?php

namespace WP_SMS\Services\Database\Migrations\Schema;

use WP_SMS\Option;
use WP_SMS\Services\Database\DatabaseFactory;

/**
 * Handles database schema migrations.
 *
 * This class is responsible for managing database schema changes across different versions.
 * It ensures that the database structure is always up to date with the current version
 * by applying necessary schema modifications.
 *
 * @package   Database
 * @version   1.0.0
 * @since     7.1
 * @author    Hooman
 */
class SchemaManager
{
    /**
     * Execute pending schema migrations if any exist.
     *
     * @return void
     */
    public static function init()
    {
        if (self::isMigrationComplete()) {
            return;
        }

        try {
            $migrationData = self::collectSchemaMigrations();

            foreach ($migrationData['versions'] as $version) {
                $migrations = $migrationData['mappings'][$version];

                foreach ($migrations as $migration) {
                    $instance = self::createMigrationInstance($migration['class']);
                    if (!$instance) {
                        continue;
                    }

                    foreach ($migration['methods'] as $method) {
                        if (method_exists($instance, $method)) {
                            $instance->$method();
                            Option::updateInGroup('version', $version, Option::DB_GROUP);
                        }
                    }
                }
            }

            Option::updateInGroup('migrated', true, Option::DB_GROUP);
        } catch (\Exception $e) {
            Option::updateInGroup('migration_status_detail', [
                'status'  => 'failed',
                'message' => $e->getMessage()
            ], Option::DB_GROUP);
        }
    }

    /**
     * Check if all schema migrations have been completed.
     *
     * @return bool Returns true if all schema migrations are complete.
     */
    private static function isMigrationComplete()
    {
        $migrated = Option::getFromGroup('migrated', Option::DB_GROUP, false);
        $check    = Option::getFromGroup('schema_check', Option::DB_GROUP, true);

        return $migrated || $check;
    }

    /**
     * Collect and prepare schema migration data.
     *
     * @return array Contains versions and their respective schema changes.
     */
    private static function collectSchemaMigrations()
    {
        $currentVersion = '7.1';
        $allVersions = [];
        $versionMappings = [];

        foreach (DatabaseFactory::migration() as $instance) {
            foreach ($instance->getMigrationSteps() as $version => $methods) {
                if (version_compare($currentVersion, $version, '>=')) {
                    continue;
                }

                $allVersions[] = $version;
                $versionMappings[$version][] = [
                    'class'   => get_class($instance),
                    'methods' => $methods,
                    'type'    => $instance->getName()
                ];
            }
        }

        if (empty($allVersions)) {
            Option::updateInGroup('migrated', true, Option::DB_GROUP);
        }

        usort($allVersions, 'version_compare');

        return [
            'versions' => $allVersions,
            'mappings' => $versionMappings
        ];
    }

    /**
     * Create an instance of the schema migration class.
     *
     * @param string $class Fully qualified class name.
     * @return object|null Instance of the class or null if it doesn't exist.
     */
    private static function createMigrationInstance($class)
    {
        if (!class_exists($class)) {
            return null;
        }
        return new $class();
    }
}
