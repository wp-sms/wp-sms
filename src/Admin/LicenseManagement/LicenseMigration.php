<?php

namespace WP_SMS\Admin\LicenseManagement;

use WP_SMS\Utils\OptionUtil;
use WP_SMS\Admin\NoticeHandler\Notice;

class LicenseMigration
{
    private $apiCommunicator;
    private $storedLicenses;
    private $oldLicenseContainerOption = 'wpsms_settings';

    /**
     * Constructor for LicenseMigration class.
     *
     * @param ApiCommunicator $apiCommunicator API communication handler.
     */
    public function __construct(ApiCommunicator $apiCommunicator)
    {
        $this->apiCommunicator = $apiCommunicator;
        $this->storedLicenses  = array_keys(LicenseHelper::getLicenses());
    }

    /**
     * Migrates all old licenses to the new license structure.
     *
     * @return void
     */
    public function migrateOldLicenses()
    {
        if (self::hasLicensesAlreadyMigrated()) {
            return;
        }

        $allLicensesMigrated = true;
        $licenses            = $this->getOldLicensesArray();
        if (!empty($licenses) && is_array($licenses)) {
            foreach ($licenses as $addonSlug => $license) {

                if ($license) {

                    if ($this->isLicenseAlreadyStored($license)) {
                        continue;
                    }

                    if (!$this->migrateLicense($addonSlug, $license)) {
                        $allLicensesMigrated = false;
                    }
                }
            }
        }

        if ($allLicensesMigrated) {
            // All licenses have been migrated successfully without any errors
            OptionUtil::saveOptionGroup('licenses_migrated', true, 'jobs');
        }
    }

    /**
     * Retrieves an array of old licenses stored in the settings.
     *
     * @return array An associative array of old licenses with add-on slugs as keys.
     */
    public function getOldLicensesArray()
    {
        $settingArray = get_option($this->oldLicenseContainerOption);

        // Filter only keys that match the pattern "license_*_key"
        $licenseFilteredArray = array_filter($settingArray, function ($key) {
            return preg_match('/^license_.*_key$/', $key);
        }, ARRAY_FILTER_USE_KEY);

        // Modify keys to remove "license_" prefix and "_key" suffix
        $modifiedLicenses = [];
        foreach ($licenseFilteredArray as $key => $value) {
            $newKey = preg_replace('/^license_(.*)_key$/', '$1', $key);
            $modifiedLicenses[$newKey] = $value;
        }

        return $modifiedLicenses;
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

    /**
     * Checks if a given license key is already stored.
     *
     * @param string $licenseKey The license key to check.
     *
     * @return bool True if the license is already stored, false otherwise.
     */
    private function isLicenseAlreadyStored($licenseKey)
    {
        return in_array($licenseKey, $this->storedLicenses);
    }

    /**
     * Attempts to migrate a single license.
     *
     * @param string $addonSlug  The slug of the add-on.
     * @param string $licenseKey The license key associated with the add-on.
     *
     * @return bool True if the license migration was successful, false otherwise.
     */
    private function migrateLicense($addonSlug, $licenseKey)
    {
        try {
            $this->apiCommunicator->validateLicense($licenseKey, $addonSlug);
        } catch (\Exception $e) {
          var_dump($e->getMessage());
            Notice::addNotice(
                sprintf(__('Failed to migrate license for %s: %s', 'wp-sms'), $addonSlug, $e->getMessage()),
                'license_migration',
                'error'
            );

            return false;
        }

        return true;
    }
}
