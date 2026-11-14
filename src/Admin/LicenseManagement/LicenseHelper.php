<?php

namespace WP_SMS\Admin\LicenseManagement;

use Exception;
use WP_SMS\Exceptions\LicenseException;
use WP_SMS\Utils\OptionUtil;

if (!defined('ABSPATH')) exit;

class LicenseHelper
{
    const LICENSE_OPTION_KEY = 'licenses';

    /**
     * Returns license stored in the WordPress database. By default only valid licenses are returned.
     *
     * @param string $status param to filter licenses by status. Could be: `valid`, `license_expired` or `all`
     * @return array
     */
    public static function getLicenses($status = 'valid')
    {
        $licenses = OptionUtil::getOptionGroup(self::LICENSE_OPTION_KEY) ?? [];

        if (!empty($status) && $status !== 'all') {
            $licenses = array_filter($licenses, function ($license) use ($status) {
                return $license['status'] === $status;
            });
        }

        return $licenses;
    }

    /**
     * Returns the stored info for a given license key.
     *
     * @param string $licenseKey
     *
     * @return object|false License data if found, false otherwise.
     */
    public static function getLicenseInfo($licenseKey)
    {
        $licenses = self::getLicenses('all');
        return $licenses[$licenseKey] ?? false;
    }

    /**
     * Returns the license key that contains the add-on with the given slug, if any.
     *
     * @param string $slug
     *
     * @return string|null License key. `null` if no valid licenses was found for this slug.
     */
    public static function getPluginLicense($slug)
    {
        // Prioritize valid licenses over expired
        $licenses = array_merge(self::getLicenses('valid'), self::getLicenses('license_expired'));

        foreach ($licenses as $key => $license) {
            if (empty($license['products'])) continue;

            if (in_array($slug, $license['products'])) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Retrieve the status of a given plugin slug.
     *
     * @param string $slug The slug of the plugin.
     *
     * @return string|null The status of the plugin, e.g. 'valid', 'expired', or null if no matching license was found.
     */
    public static function getPluginLicenseStatus($slug)
    {
        $licenseKey = self::getPluginLicense($slug);
        $status     = self::getLicenseInfo($licenseKey);

        return $status['status'] ?? null;
    }

    /**
     * Checks if the given plugin slug has a valid license.
     *
     * @param string $slug The slug of the plugin.
     *
     * @return bool True if the plugin has a valid license, false otherwise.
     */
    public static function isPluginLicenseValid($slug = 'wp-sms-pro')
    {
        $status = self::getPluginLicenseStatus($slug);
        return $status === 'valid';
    }

    /**
     * Checks if the given plugin is expired.
     *
     * @param string $slug The slug of the plugin.
     */
    public static function isPluginLicenseExpired($slug)
    {
        $status = self::getPluginLicenseStatus($slug);
        return $status === 'license_expired';
    }

    /**
     * Store license details in the WordPress database.
     *
     * @param string $licenseKey
     * @param object $license
     */
    public static function storeLicense($licenseKey, $licenseData)
    {
        $data = [
            'status'      => $licenseData->status,
            'type'        => $licenseData->license_details->type ?? null,
            'sku'         => $licenseData->license_details->sku ?? null,
            'max_domains' => $licenseData->license_details->max_domains ?? null,
            'valid_until' => $licenseData->license_details->valid_until ?? null,
            'user'        => $licenseData->license_details->user ?? null,
            'products'    => isset($licenseData->products) ? wp_list_pluck($licenseData->products, 'slug') : null,
            // How to renew and what it costs, as the licence server quotes it (null when it sends none).
            'renewal'     => self::normalizeRenewal($licenseData->renewal ?? $licenseData->license_details->renewal ?? null),
        ];

        OptionUtil::saveOptionGroup($licenseKey, $data, self::LICENSE_OPTION_KEY);
    }

    /**
     * Keep only the renewal fields the plugin shows: link, offer and price.
     *
     * @param object|array|null $renewal The `renewal` block from the licence server.
     * @return array|null
     */
    public static function normalizeRenewal($renewal)
    {
        $renewal = json_decode(wp_json_encode($renewal), true);

        if (empty($renewal['renew_url'])) {
            return null;
        }

        $offer = $renewal['offer'] ?? null;
        $price = $renewal['price'] ?? null;

        return [
            'renew_url' => esc_url_raw($renewal['renew_url']),
            'offer'     => is_array($offer) ? [
                'code'           => sanitize_text_field($offer['code'] ?? ''),
                'discount_type'  => sanitize_text_field($offer['discount_type'] ?? ''),
                'discount_value' => $offer['discount_value'] ?? null,
                'expires_at'     => sanitize_text_field($offer['expires_at'] ?? ''),
            ] : null,
            'price'     => is_array($price) ? [
                'currency'     => sanitize_text_field($price['currency'] ?? ''),
                'full'         => sanitize_text_field($price['full'] ?? ''),
                'with_offer'   => isset($price['with_offer']) ? sanitize_text_field($price['with_offer']) : null,
                'tax_included' => !empty($price['tax_included']),
            ] : null,
        ];
    }

    /**
     * The one place every number about licenses comes from: header, menu badge, notification, Add-Ons page.
     *
     * - total:         every add-on the store sells
     * - licensed:      add-ons with a valid license (installed or not)
     * - missing:       installed, no valid license
     * - expired:       installed, license expired
     * - inactive:      installed and licensed, but turned off
     * - not_installed: not installed
     * - attention:     missing + expired + inactive (what the menu badge counts)
     *
     * @return array{total:int, licensed:int, installed:int, missing:int, expired:int, inactive:int, not_installed:int, attention:int, renew:?array}
     */
    public static function getLicenseSummary()
    {
        static $summary = null;

        if ($summary !== null) {
            return $summary;
        }

        $result = ['total' => 0, 'licensed' => 0, 'installed' => 0, 'missing' => 0, 'expired' => 0, 'inactive' => 0, 'not_installed' => 0, 'attention' => 0, 'renew' => null];

        try {
            foreach (\WP_SMS\Admin\LicenseManagement\Plugin\PluginHelper::getRemotePlugins() as $plugin) {
                $result['total']++;

                $installed = $plugin->isInstalled();
                $valid     = $plugin->isLicenseValid();
                $expired   = $plugin->isLicenseExpired();

                if ($valid) {
                    $result['licensed']++;
                }

                if (!$installed) {
                    $result['not_installed']++;
                    continue;
                }

                $result['installed']++;

                if ($expired) {
                    $result['expired']++;
                } elseif (!$valid) {
                    $result['missing']++;
                } elseif (!$plugin->isActivated()) {
                    $result['inactive']++;
                }
            }
        } catch (\Throwable $e) {
            // Do not keep half-counted numbers for the rest of the request.
            return $result;
        }

        $result['attention'] = $result['missing'] + $result['expired'] + $result['inactive'];

        foreach (self::getLicenses('license_expired') as $license) {
            if (!empty($license['renewal']['renew_url'])) {
                $result['renew'] = $license['renewal'];
                break;
            }
        }

        $summary = $result;

        return $summary;
    }

    /**
     * Same numbers as the summary, under the names the notification and header already use.
     *
     * @return array{count:int, missing:int, expired:int, inactive:int, renew:?array}
     */
    public static function getLicenseAlerts()
    {
        $summary = self::getLicenseSummary();

        return [
            'count'    => $summary['attention'],
            'missing'  => $summary['missing'],
            'expired'  => $summary['expired'],
            'inactive' => $summary['inactive'],
            'renew'    => $summary['renew'],
        ];
    }

    /**
     * Update license in the database
     *
     * @param string $licenseKey
     * @param object $license
     */
    public static function updateLicense($licenseKey, $licenseData)
    {
        OptionUtil::saveOptionGroup($licenseKey, $licenseData, self::LICENSE_OPTION_KEY);
    }

    /**
     * Removes a license from WordPress database.
     *
     * @param string $licenseKey
     *
     * @return void
     */
    public static function removeLicense($licenseKey)
    {
        OptionUtil::deleteOptionGroup($licenseKey, self::LICENSE_OPTION_KEY);
    }

    /**
     * Checks if user has any valid license or not.
     *
     * @return bool
     */
    public static function isValidLicenseAvailable()
    {
        return !empty(self::getLicenses());
    }

    /**
     * Checks all stored licenses to see if any of them is premium.
     *
     * @return string|null License key. `null` if no premium licenses were found.
     */
    public static function isPremiumLicenseAvailable()
    {
        foreach (self::getLicenses() as $key => $data) {
            if (!empty($data['sku']) && $data['sku'] === 'all-in-one') {
                return $key;
            }
        }

        return null;
    }

    /**
     * Checks all stored licenses to see if they are valid or not.
     *
     * @return void
     */
    public static function checkLicensesStatus()
    {
        $apiCommunicator = new ApiCommunicator();
        $licenses        = LicenseHelper::getLicenses('all');

        foreach ($licenses as $key => $data) {
            try {
                $licenseData = $apiCommunicator->validateLicense($key);
                LicenseHelper::storeLicense($key, $licenseData);
            } catch (LicenseException $e) {
                // If status is empty, do nothing (probably server error, or connection issue)
                if (!$e->getStatus()) continue;

                // If license is expired, update the status
                if ($e->getStatus() === 'license_expired') {
                    $data['status']  = $e->getStatus();
                    $data['renewal'] = $e->getRenewal() ?: ($data['renewal'] ?? null);
                    LicenseHelper::updateLicense($key, $data);
                    continue;
                }

                // If license is invalid, remove the license
                LicenseHelper::removeLicense($key);
            }
        }
    }

    /**
     * Checks if the Pro plugin is both licensed and active.
     *
     * @param string $slug The slug of the plugin, default is 'wp-sms-pro'.
     * @return bool
     */
    public static function isPluginLicensedAndActive($slug = 'wp-sms-pro')
    {
        // Ensure the license is valid
        if (!self::isPluginLicenseValid($slug)) {
            return false;
        }

        // Check plugin activation status
        $pluginHandler = new \WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler();

        return $pluginHandler->isPluginActive($slug);
    }

}