<?php

namespace WP_SMS\Admin\LicenseManagement;

use WP_SMS\Utils\OptionUtil;
use WP_SMS\Admin\NoticeHandler\Notice;

class LicenseMigration
{
    private $apiCommunicator;
    private $storedLicenses;
    private $optionMap = [
        'wp-sms-advanced-reporting' => 'wpsms_advanced_reporting_settings',
        'wp-sms-customization'      => 'wpsms_customization_settings',
        'wp-sms-widgets'            => 'wpsms_widgets_settings',
        'wp-sms-realtime-stats'     => 'wpsms_realtime_stats_settings',
        'wp-sms-mini-chart'         => 'wpsms_mini_chart_settings',
        'wp-sms-rest-api'           => 'wpsms_rest_api_settings',
        'wp-sms-data-plus'          => 'wpsms_data_plus_settings',
    ];

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

        foreach ($this->optionMap as $addonSlug => $optionName) {
            $licenseKey = $this->fetchOldLicenseKey($optionName);

            if ($licenseKey) {
                if ($this->isLicenseAlreadyStored($licenseKey)) {
                    $this->removeOldLicenseKey($optionName);
                    continue;
                }

                if (!$this->migrateLicense($addonSlug, $licenseKey)) {
                    $allLicensesMigrated = false;
                }
            }
        }

        if ($allLicensesMigrated) {
            // All licenses have been migrated successfully without any errors
            OptionUtil::saveOptionGroup('licenses_migrated', true, 'jobs');
        }
    }

    /**
     * Checks if all licenses have already been migrated.
     *
     * @return bool
     */
    public static function hasLicensesAlreadyMigrated()
    {
        return OptionUtil::getOptionGroup('jobs', 'licenses_migrated');
    }

    /**
     * Fetches the license key from the old option structure.
     *
     * @param string $optionName
     *
     * @return string|null
     */
    private function fetchOldLicenseKey($optionName)
    {
        $licenseData = get_option($optionName);
        return $licenseData['license_key'] ?? null;
    }

    /**
     * Removes the license key from the old option structure.
     *
     * @param string $optionName
     *
     * @param void
     */
    private function removeOldLicenseKey($optionName)
    {
        $licenseData = get_option($optionName);

        if (!isset($licenseData['license_key'])) {
            return;
        }

        unset($licenseData['license_key']);

        update_option($optionName, $licenseData);
    }

    /**
     * Checks if the license is already stored.
     *
     * @param string $licenseKey
     *
     * @return bool
     */
    private function isLicenseAlreadyStored($licenseKey)
    {
        return in_array($licenseKey, $this->storedLicenses);
    }

    /**
     * Tries to migrate a single license.
     *
     * @param string $addonSlug
     * @param string $licenseKey
     *
     * @return bool
     */
    private function migrateLicense($addonSlug, $licenseKey)
    {
        try {
            $this->apiCommunicator->validateLicense($licenseKey, $addonSlug);
        } catch (\Exception $e) {
            Notice::addNotice(
                // translators: 1: Add-on slug - 2: Error message.
                sprintf(__('Failed to migrate license for %s: %s', 'wp-sms'), $addonSlug, $e->getMessage()),
                'license_migration',
                'error'
            );

            return false;
        }

        return true;
    }
}
