<?php

namespace WP_SMS\Core\Operations;

use WP_SMS\Core\AbstractCore;
use WP_SMS\Option;
use WP_SMS\Services\Database\Managers\TableHandler;
use WP_SMS\Services\Database\Managers\SchemaMaintainer;

/**
 * Handles plugin activation tasks.
 *
 * Runs on activation (and per site when network-activated on multisite) to check
 * fresh-install state, create required tables, ensure default options exist,
 * and store the current plugin version.
 *
 * @see register_activation_hook()
 * @package WP_SMS\Core\Operations
 */
class Activator extends AbstractCore
{
    /**
     * Activator constructor.
     *
     * @param bool $networkWide
     * @return void
     */
    public function __construct($networkWide = false)
    {
        parent::__construct($networkWide);
        $this->execute();
    }

    /**
     * Execute the core function.
     *
     * @return void
     */
    public function execute()
    {
        $this->loadRequiredFiles();

        if (is_multisite() && $this->networkWide) {
            $blogIds = $this->wpdb->get_col("SELECT `blog_id` FROM {$this->wpdb->blogs}");
            foreach ($blogIds as $blogId) {
                switch_to_blog($blogId);
                $this->checkIsFresh();
                TableHandler::createAllTables();
                restore_current_blog();
            }
        } else {
            $this->checkIsFresh();
            TableHandler::createAllTables();
        }

        $this->initializeVersion();
        SchemaMaintainer::repair(true);
    }

    /**
     * Ensures the plugin version is stored in the database.
     *
     * @return void
     */
    private function initializeVersion()
    {
        $version = Option::getFromGroup('version', Option::DB_GROUP);

        if (!empty($version)) {
            return;
        }

        $this->updateVersion();
    }

    /**
     * Load required files for activation.
     *
     * @return void
     */
    protected function loadRequiredFiles()
    {
        require_once WP_SMS_DIR . 'includes/class-wpsms-option.php';
        require_once WP_SMS_DIR . 'includes/libraries/wp-background-processing/wp-async-request.php';
        require_once WP_SMS_DIR . 'includes/libraries/wp-background-processing/wp-background-process.php';
    }
}
