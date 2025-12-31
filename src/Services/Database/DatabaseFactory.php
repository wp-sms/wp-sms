<?php

namespace WP_SMS\Services\Database;

use WP_SMS\Option;
use WP_SMS\Services\Database\Migrations\Schema\SchemaMigration;
use WP_SMS\Services\Database\Operations\AbstractTableOperation;
use WP_SMS\Services\Database\Operations\Create;
use WP_SMS\Services\Database\Operations\Drop;
use WP_SMS\Services\Database\Operations\Insert;
use WP_SMS\Services\Database\Operations\Inspect;
use WP_SMS\Services\Database\Operations\InspectColumns;
use WP_SMS\Services\Database\Operations\Repair;
use WP_SMS\Services\Database\Operations\Select;
use WP_SMS\Services\Database\Operations\Update;

/**
 * Factory for creating database operation and migration instances.
 *
 * This class provides methods to create specific operations (e.g., create, update, drop)
 * and manage different migration types (e.g., schema, data).
 *
 * @package   Database
 * @version   1.0.0
 * @since     7.1
 * @author    Hooman
 */
class DatabaseFactory
{
    /**
     * Mapping of operation names to their corresponding classes.
     *
     * @var array
     */
    private static $operations = [
        'create'          => Create::class,
        'update'          => Update::class,
        'drop'            => Drop::class,
        'inspect'         => Inspect::class,
        'insert'          => Insert::class,
        'select'          => Select::class,
        'repair'          => Repair::class,
        'inspect_columns' => InspectColumns::class,
    ];

    /**
     * Mapping of migration types to their corresponding classes.
     *
     * @var array
     */
    private static $migrationTypes = [
        'schema' => SchemaMigration::class,
    ];

    /**
     * Cache of instantiated table operations.
     *
     * @var array<string, AbstractTableOperation>
     */
    private static $operationInstance = [];

    /**
     * Create an instance of a specific table operation.
     *
     * @param string $operation The name of the operation (e.g., 'create', 'drop').
     * @return AbstractTableOperation An instance of the corresponding operation class.
     * @throws \InvalidArgumentException If the operation is invalid or the class does not exist.
     */
    public static function table($operation)
    {
        $operation = strtolower($operation);

        if (!empty(self::$operationInstance[$operation])) {
            return self::$operationInstance[$operation];
        }

        if (!isset(self::$operations[$operation])) {
            throw new \InvalidArgumentException("Invalid operation: {$operation}");
        }

        $providerClass = self::$operations[$operation];

        if (!class_exists($providerClass)) {
            throw new \InvalidArgumentException("Class not exist: {$providerClass}");
        }

        self::$operationInstance[$operation] = new $providerClass();

        return self::$operationInstance[$operation];
    }

    /**
     * Create instances of all registered migration types.
     *
     * @return array An array of migration instances.
     */
    public static function migration()
    {
        $migrationInstances = [];

        foreach (self::$migrationTypes as $migrationClass) {
            if (!class_exists($migrationClass)) {
                continue;
            }

            $migrationInstances[] = new $migrationClass();
        }

        return $migrationInstances;
    }

    /**
     * Compare the current database version with a required version.
     *
     * @param string $requiredVersion The version to compare against (e.g., "1.2.3").
     * @param string $operation The comparison operator ('<', '<=', '>', '>=', '==', '!=').
     * @return bool Returns true if the comparison condition is met, false otherwise.
     */
    public static function compareCurrentVersion($requiredVersion, $operation)
    {
        $version = Option::getFromGroup('version', Option::DB_GROUP);

        if (empty($version)) {
            return false;
        }

        return version_compare($version, $requiredVersion, $operation);
    }
}
