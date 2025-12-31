<?php

namespace WP_SMS\Services\Database\Migrations\Queue;

use WP_SMS\Abstracts\BaseMigrationOperation;

/**
 * Queue migration class for handling database migration steps.
 *
 * This class extends BaseMigrationOperation to provide specific migration
 * functionality for queued operations. It handles various setting updates
 * and data transformations during the migration process.
 *
 * ## How Queue Migrations Work
 *
 * Queue migrations are triggered manually by the user via an admin notice.
 * Unlike schema migrations, they don't run automatically on update.
 * This is useful for data transformations that might take time.
 *
 * ## When to Use Queue Migrations
 *
 * - Migrating data from old format to new format
 * - Cleaning up deprecated settings
 * - Transforming existing data in bulk
 * - Any operation that doesn't change table structure
 *
 * ## Adding a New Migration
 *
 * 1. Add an entry to `$migrationSteps` with unique key => method name
 * 2. Create the corresponding method in this class
 * 3. User will see a notice to run the migration
 *
 * @package   Database
 * @version   1.0.0
 * @since     7.1
 * @author    Hooman
 */
class QueueMigration extends BaseMigrationOperation
{
    /**
     * Array of migration steps with their corresponding method names.
     *
     * Each key represents a migration step identifier, and the value
     * is the corresponding method name to execute for that step.
     * The methods are called sequentially during the migration process.
     *
     * @var array<string, string> Array mapping step names to method names.
     */
    protected $migrationSteps = [];
}
