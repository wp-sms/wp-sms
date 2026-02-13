<?php

namespace WP_SMS\Admin\LicenseManagement;

use Exception;
use WP_SMS\Components\RemoteRequest;
use WP_SMS\Exceptions\LicenseException;
use WP_SMS\Utils\AdminHelper;
use WP_SMS\Traits\TransientCacheTrait;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHelper;

if (!defined('ABSPATH')) exit;

class ApiCommunicator
{
    use TransientCacheTrait;

    private $apiUrl = 'https://wp-sms-pro.com' . '/wp-json/wp-license-manager/v1';

    /**
     * Cache duration for failed requests (5 minutes).
     * Short duration allows users to retry quickly after fixing license issues
     * (e.g., adding domain to license, renewing expired license).
     */
    const NEGATIVE_CACHE_DURATION = 5 * MINUTE_IN_SECONDS;

    /**
     * Get the list of products (add-ons) from the API and cache it for 1 week.
     *
     * @return array
     * @throws Exception if there is an error with the API call
     */
    public function getProducts()
    {
        try {
            $remoteRequest = new RemoteRequest('GET', "{$this->apiUrl}/product/list");
            $addons       = $remoteRequest->execute(false, true, WEEK_IN_SECONDS);

            if (empty($addons) || !is_array($addons)) {
                throw new Exception(
                    /* translators: %s: API URL */
                    sprintf(__('No products were found. The API returned an empty response from the following URL: %s', 'wp-sms'), "{$this->apiUrl}/product/list")
                );
            }

        } catch (Exception $e) {
            throw new Exception(
            // translators: %s: Error message.
                sprintf(__('Unable to retrieve product list from the remote server, %s. Please check the remote server connection or your remote work configuration.', 'wp-sms'), $e->getMessage())
            );
        }

        return $addons;
    }

    /**
     * Generate a cache key for product info.
     *
     * The key is site-specific to handle:
     * - Multisite with subdomains (each subsite may have different license)
     * - Multisite with subdirectories
     * - Single site with multilingual plugins (WPML, Polylang) where home_url() varies by language
     *
     * @param string $addonSlug  The add-on slug.
     * @param string $licenseKey The license key.
     *
     * @return string The cache key.
     */
    private function getProductInfoCacheKey($addonSlug, $licenseKey)
    {
        // Use blog ID for multisite to ensure each subsite has its own cache
        // For single sites, this will always be 1
        $siteIdentifier = get_current_blog_id();

        return 'wp_sms_product_info_' . md5($addonSlug . '_' . $licenseKey . '_' . $siteIdentifier);
    }

    /**
     * Clear cached product info for a specific add-on and license.
     *
     * Call this method when license is validated/changed to ensure fresh data.
     *
     * @param string $licenseKey The license key.
     * @param string $addonSlug  The add-on slug (optional, clears all if not provided).
     *
     * @return void
     */
    public function clearProductInfoCache($licenseKey, $addonSlug = null)
    {
        if ($addonSlug) {
            delete_transient($this->getProductInfoCacheKey($addonSlug, $licenseKey));
        } else {
            // Clear cache for all known add-ons when no specific slug provided
            foreach (array_keys(PluginHelper::$plugins) as $addon) {
                delete_transient($this->getProductInfoCacheKey($addon, $licenseKey));
            }
        }
    }

    /**
     * Get the product info for the specified add-on
     *
     * @param string $licenseKey
     * @param string $addonSlug
     *
     * @return object|null The product info if found, null otherwise
     * @throws Exception if the API call fails
     */
    public function getProductInfo($licenseKey, $addonSlug)
    {
        $cacheKey = $this->getProductInfoCacheKey($addonSlug, $licenseKey);

        // Check for negative cache (failed requests cached for 5 minutes)
        $cached = get_transient($cacheKey);
        if ($cached !== false && is_object($cached) && isset($cached->_negative_cache)) {
            return null;
        }

        try {
            $remoteRequest = new RemoteRequest('GET', "{$this->apiUrl}/product/download", [
                'license_key' => $licenseKey,
                'domain'      => home_url(),
                'plugin_slug' => $addonSlug,
            ]);

            // Use custom cache key for proper multisite/multilingual support
            return $remoteRequest->execute(true, true, DAY_IN_SECONDS, $cacheKey);

        } catch (Exception $e) {
            // Negative cache: store failed requests for 5 minutes to prevent API hammering
            // while still allowing users to retry quickly after fixing issues
            set_transient($cacheKey, (object)['_negative_cache' => true], self::NEGATIVE_CACHE_DURATION);
            throw $e;
        }
    }

    /**
     * Validate the license and get the status of licensed products.
     *
     * @param string $licenseKey
     * @param string $product Optional param to check whether the license is valid for a particular product, or not
     *
     * @return object License status
     * @throws Exception if the API call fails
     */
    public function validateLicense($licenseKey, $product = false)
    {
        if (empty($licenseKey) || !AdminHelper::isStringLengthBetween($licenseKey, 32, 40) || !preg_match('/^[a-zA-Z0-9-]+$/', $licenseKey)) {
            throw new LicenseException(
                esc_html__('License key is not valid. Please enter a valid license and try again.', 'wp-sms'),
                'invalid_license'
            );
        }

        $remoteRequest = new RemoteRequest('GET', "{$this->apiUrl}/license/status", [
            'license_key' => $licenseKey,
            'domain'      => home_url(),
        ]);

        $licenseData = $remoteRequest->execute(false, false);

        if (empty($licenseData)) {
            throw new LicenseException(__('Invalid license response!', 'wp-sms'));
        }

        if (empty($licenseData->license_details)) {
            $message = isset($licenseData) && is_object($licenseData) && isset($licenseData->message)
                ? $licenseData->message
                : esc_html__('Unknown error!', 'wp-sms');

            $status = isset($licenseData) && is_object($licenseData) && isset($licenseData->status)
                ? $licenseData->status
                : '';

            $code = isset($licenseData) && is_object($licenseData) && isset($licenseData->code)
                ? intval($licenseData->code)
                : 0;

            throw new LicenseException(
                $message,
                $status,
                $code
            );

        }

        if (!empty($product)) {
            $productSlugs = array_column($licenseData->products, 'slug');

            if (!in_array($product, $productSlugs, true)) {
                /* translators: %s: Add-On name */
                throw new LicenseException(sprintf(__('The license is not related to the requested Add-On <b>%s</b>.', 'wp-sms'), $product));
            }
        }

        LicenseHelper::storeLicense($licenseKey, $licenseData);

        // Clear product info cache on successful license validation
        // This ensures fresh download URLs after license changes (renewal, domain addition, etc.)
        $this->clearProductInfoCache($licenseKey);

        return $licenseData;
    }
}
