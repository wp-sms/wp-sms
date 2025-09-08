<?php

namespace WP_SMS\Core\Operations;

use WP_SMS\Core\AbstractCore;
use WP_SMS\Services\Database\Managers\SchemaMaintainer;
use WP_SMS\Services\Database\Managers\TableHandler;
use WP_SMS\Services\Database\Migrations\Schema\SchemaManager;

/**
 * Handles update-time migrations and cleanup.
 *
 * Runs on init when a version change is detected; ensures tables exist, executes
 * legacy migrations, updates the stored version, and bootstraps the schema manager.
 * Also adjusts schedules, options, and cached data as needed.
 *
 * @package WP_SMS\Core\Operations
 */
class Updater extends AbstractCore
{
    /**
     * Updater constructor.
     *
     * @return void
     */
    public function __construct($networkWide = false)
    {
        parent::__construct($networkWide);
        add_action('init', [$this, 'execute']);
    }

    /**
     * Execute the core function.
     *
     * @return void
     */
    public function execute()
    {
        if (is_multisite()) {
            $this->initializeDefaultOptions();
        }

        if (!$this->isUpdated()) {
            return;
        }

        $this->checkIsFresh();
        TableHandler::createAllTables();
        $this->legacyMigrations();
        $this->updateVersion();

        SchemaManager::init();
        SchemaMaintainer::repair(true);
    }

    /**
     * Execute the legacy migrations.
     *
     * @return void
     */
    private function legacyMigrations()
    {
    }

}