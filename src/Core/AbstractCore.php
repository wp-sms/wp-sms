<?php

namespace WP_SMS\Core;

use WP_SMS\Option;

/**
 * Base class containing shared initialization logic and utilities for core components.
 *
 * Provides helpers for option setup, required file loading, version checks and updates,
 * and enforces an `execute()` method that concrete subclasses must implement.
 *
 * @package WP_SMS\Core
 */
abstract class AbstractCore
{
    /**
     * Stores the current plugin version retrieved from the database.
     *
     * @var string
     */
    protected $currentVersion;

    /**
     * Stores the latest version of the plugin defined by the codebase.
     *
     * @var string
     */
    protected $latestVersion;

    /**
     * Whether operations are being performed network-wide (multisite network activation).
     *
     * @var bool
     */
    protected $networkWide = false;

    /**
     * WordPress database access object.
     *
     * @var \wpdb
     */
    protected $wpdb;

    /**
     * AbstractCore constructor.
     *
     * @param bool $networkWide
     * @return void
     */
    public function __construct($networkWide = false)
    {
        $this->latestVersion = WP_SMS_VERSION;
        $this->networkWide   = (bool) $networkWide;

        $this->setWpdb();
    }

    /**
     * Initialize the wpdb property from the global $wpdb.
     */
    private function setWpdb()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
    }

    /**
     * Initialize default options.
     *
     * @return void
     */
    protected function initializeDefaultOptions()
    {
        $this->loadRequiredFiles();

        $options = get_option('wpsms_settings');
        if (empty($options) || !is_array($options)) {
            update_option('wpsms_settings', []);
        }
    }

    /**
     * Load required files.
     *
     * @return void
     */
    protected function loadRequiredFiles()
    {
        require_once WP_SMS_DIR . 'includes/class-wpsms-option.php';
    }

    /**
     * Checks whether the plugin is a fresh installation.
     *
     * @return void
     */
    protected function checkIsFresh()
    {
        $version = Option::getFromGroup('version', Option::DB_GROUP);

        if (empty($version)) {
            Option::updateInGroup('is_fresh', true, Option::DB_GROUP);
        } else {
            Option::updateInGroup('is_fresh', false, Option::DB_GROUP);
        }

        $installationTime = Option::getFromGroup('installation_time', Option::DB_GROUP);

        if (empty($installationTime)) {
            Option::updateInGroup('installation_time', time(), Option::DB_GROUP);
        }
    }

    /**
     * Checks whether the plugin is updated.
     *
     * @return bool
     */
    protected function isUpdated()
    {
        $this->currentVersion = Option::getFromGroup('version', Option::DB_GROUP, WP_SMS_VERSION);

        return $this->currentVersion != $this->latestVersion;
    }

    /**
     * Update the plugin version.
     *
     * @return void
     */
    protected function updateVersion()
    {
        Option::updateInGroup('version', $this->latestVersion, Option::DB_GROUP);
    }

    /**
     * Execute the core function.
     *
     * @return void
     */
    abstract public function execute();
}
