<?php

namespace WP_SMS\Services\Database\Migrations\Schema;

use Exception;
use WP_SMS\Abstracts\BaseMigrationOperation;
use WP_SMS\Services\Database\DatabaseFactory;

/**
 * Manages migrations related to database schema.
 *
 * This class extends BaseMigrationOperation to provide specific migration
 * functionality for schema changes. It handles table structure modifications
 * like adding, modifying, or dropping columns.
 *
 * ## How Schema Migrations Work
 *
 * Schema migrations run automatically when the plugin version changes.
 * They are executed in version order, so older migrations run before newer ones.
 *
 * ## Adding a New Migration
 *
 * 1. Add an entry to `$migrationSteps` with the version as key
 * 2. Create the corresponding method(s) in this class
 * 3. The migration will run automatically on the next plugin update
 *
 * ## Available Database Operations
 *
 * Use `DatabaseFactory::table()` with these operations:
 * - `update` - Add, modify, rename, or drop columns
 * - `inspect` - Check if table exists
 * - `inspect_columns` - Get column information
 *
 * ## Example Usage
 *
 * ```php
 * protected $migrationSteps = [
 *     '7.2.0' => [
 *         'addSubscriberEmailColumn',
 *         'modifyMessageColumnType',
 *     ],
 * ];
 *
 * protected function addSubscriberEmailColumn()
 * {
 *     DatabaseFactory::table('update')
 *         ->setName('sms_subscribes')
 *         ->setArgs([
 *             'add' => [
 *                 'email' => 'VARCHAR(255) NULL AFTER `mobile`',
 *             ],
 *         ])
 *         ->execute();
 * }
 * ```
 *
 * @package   Database
 * @version   1.0.0
 * @since     7.1
 * @author    Hooman
 */
class SchemaMigration extends BaseMigrationOperation
{
    /**
     * The name of the migration operation.
     *
     * @var string
     */
    protected $name = 'schema';

    /**
     * The list of migration steps for this operation.
     *
     * This array maps version numbers to their corresponding migration methods.
     * Each version key represents a database schema migration that needs to be applied
     * for that specific version. The associated value is an array of method names
     * that should be executed for the migration step.
     *
     * @var array
     */
    protected $migrationSteps = [];
}
