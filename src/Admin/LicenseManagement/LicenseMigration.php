<?php

namespace WP_SMS\Admin\LicenseManagement;

use WP_SMS\Utils\OptionUtil;

if (!defined('ABSPATH')) exit;

/**
 * Class LicenseMigration
 *
 * @deprecated License migration is no longer supported in the free plugin.
 */
class LicenseMigration
{
    /**
     * Constructor for LicenseMigration class.
     *
     * @deprecated License migration is handled by the Pro plugin.
     */
    public function __construct()
    {
    }

    /**
     * Migrates all old licenses to the new license structure.
     *
     * @deprecated License migration is handled by the Pro plugin.
     * @return void
     */
    public function migrateOldLicenses()
    {
        // License migration is now handled by the Pro plugin
        return;
    }

    /**
     * Checks if all licenses have already been migrated.
     *
     * @return bool True if licenses have been migrated, false otherwise.
     */
    public static function hasLicensesAlreadyMigrated()
    {
        return OptionUtil::getOptionGroup('jobs', 'licenses_migrated');
    }
}
