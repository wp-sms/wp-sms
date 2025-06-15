<?php

namespace WP_SMS\Services\Database\Managers;

use WP_SMS\Utils\OptionUtil as Option;
use WP_SMS\Services\Database\DatabaseFactory;

/**
 * Handles database migrations (schema + data) synchronously.
 */
class MigrationHandler
{
    /**
     * Initialize migration trigger on admin load.
     *
     * @return void
     */
    public static function init()
    {
        add_action('admin_init', [self::class, 'runMigrations']);
    }

    /**
     * Run all pending migrations (schema + data).
     *
     * @return void
     */
    public static function runMigrations()
    {
        if (self::isMigrationComplete()) {
            return;
        }

        try {
            $migrationData = self::collectMigrationData();

            foreach ($migrationData['versions'] as $version) {
                $migrations = $migrationData['mappings'][$version];

                foreach ($migrations as $migration) {
                    $instance = self::createMigrationInstance($migration['class']);
                    if (!$instance) {
                        continue;
                    }

                    foreach ($migration['methods'] as $method) {
                        if (!method_exists($instance, $method)) {
                            continue;
                        }

                        $result = $instance->$method();

                        // If it's a data migration that returns tasks, execute inline
                        if (is_array($result)) {
                            foreach ($result as $task) {
                                $instance->$method($task); // Assuming method handles a task argument too
                            }
                        }

                        Option::saveOptionGroup('version', $version, 'db');
                    }
                }
            }

            Option::saveOptionGroup('migrated', true, 'db');
            Option::saveOptionGroup('migration_status_detail', ['status' => 'done'], 'db');

        } catch (\Exception $e) {
            Option::saveOptionGroup('migration_status_detail', [
                'status'  => 'failed',
                'message' => $e->getMessage()
            ], 'db');
        }
    }

    /**
     * Check if migration has already run.
     *
     * @return bool
     */
    private static function isMigrationComplete()
    {
        return Option::getOptionGroup('db', 'migrated', false) || Option::getOptionGroup('db', 'check', true);
    }

    /**
     * Collect migration steps for versions newer than current.
     *
     * @return array
     */
    private static function collectMigrationData()
    {
        $currentVersion  = Option::getOptionGroup('db', 'version', '0.0.0');
        $allVersions     = [];
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
            Option::saveOptionGroup('migrated', true, 'db');
        }

        usort($allVersions, 'version_compare');

        return [
            'versions' => $allVersions,
            'mappings' => $versionMappings
        ];
    }

    /**
     * Instantiate migration class if available.
     *
     * @param string $class
     * @return object|null
     */
    private static function createMigrationInstance($class)
    {
        return class_exists($class) ? new $class() : null;
    }
}
