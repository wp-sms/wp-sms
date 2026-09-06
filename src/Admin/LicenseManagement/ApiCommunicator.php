<?php

namespace WP_SMS\Admin\LicenseManagement;

use Exception;
use WP_SMS\Components\RemoteRequest;
use WP_SMS\Exceptions\LicenseException;
use WP_SMS\Traits\TransientCacheTrait;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHelper;

if (!defined('ABSPATH')) exit;

class ApiCommunicator
{
    use TransientCacheTrait;

    private $apiUrl = 'https://my.wsms.io/wp-json/wp-license-manager/v1';

    /**
     * How long a transport failure is remembered before we try again (5 minutes).
     *
     * A timeout, a DNS failure or a 5xx says nothing about the licence — the answer may
     * be different in a moment — so this stays short. It is the starting point of the
     * backoff in {@see self::transientRetryDelay()}, not a fixed interval.
     */
    const NEGATIVE_CACHE_DURATION = 5 * MINUTE_IN_SECONDS;

    /**
     * How long a refusal from the licence server is remembered (12 hours).
     *
     * An expired or suspended licence, or a domain that is not on it, is a decision the
     * server has already made. Asking again in five minutes cannot change it, and 288
     * asks a day per add-on per site is what made a single refused install send 576
     * requests in a day (#539). Twelve hours is a working day either side, so a customer
     * who fixes a licence in the morning is not still refused in the afternoon — and
     * {@see self::clearProductInfoCache()} clears this the moment a licence is validated,
     * so in practice they wait no time at all.
     */
    const AUTHORITATIVE_CACHE_DURATION = 12 * HOUR_IN_SECONDS;

    /**
     * The longest a transport failure is remembered once the backoff has stretched.
     */
    const MAX_TRANSIENT_CACHE_DURATION = 6 * HOUR_IN_SECONDS;

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
            // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Message is escaped via esc_html()
            throw new Exception(
                /* translators: %s: Error message. */
                sprintf(__('Unable to retrieve product list from the remote server, %s. Please check the remote server connection or your remote work configuration.', 'wp-sms'), esc_html($e->getMessage()))
            );
            // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
     * Generate the cache key for a refusal.
     *
     * Deliberately NOT keyed on the blog ID, the way the success cache above is. The
     * server judges the address we send it, so the address is the unit a refusal belongs
     * to — and `home_url()` is exactly what we send.
     *
     * What that buys, case by case:
     *
     * - **Subdirectory multisite** — every subsite shares one host, so the whole network
     *   shares one entry and asks once instead of once per subsite. This is the case that
     *   turned one refused licence into hundreds of requests an hour.
     * - **Subdomain multisite** — each subsite has its own host, so each keeps its own
     *   entry. It must: the server may well allow one subdomain and refuse another, and a
     *   shared entry would silence a subsite that was never refused.
     * - **Multilingual single site** — WPML and Polylang can vary `home_url()` per
     *   language while the blog ID stays 1. Keying on the address means each variant is
     *   remembered as the server actually answered it.
     *
     * @param string $addonSlug  The add-on slug.
     * @param string $licenseKey The license key.
     *
     * @return string The cache key.
     */
    private function getRefusalCacheKey($addonSlug, $licenseKey)
    {
        return 'wp_sms_license_refusal_' . md5($addonSlug . '_' . $licenseKey . '_' . $this->requestDomain());
    }

    /**
     * The address sent to the licence server, and the thing it judges.
     *
     * @return string
     */
    private function requestDomain()
    {
        return home_url();
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
        // Both caches, always. A refusal now lasts twelve hours, so forgetting to clear
        // it here is the difference between a renewal working immediately and the
        // customer being told their licence is still expired for the rest of the day.
        if ($addonSlug) {
            delete_transient($this->getProductInfoCacheKey($addonSlug, $licenseKey));
            $this->deleteRefusal($this->getRefusalCacheKey($addonSlug, $licenseKey));

            return;
        }

        // Clear cache for all known add-ons when no specific slug provided
        foreach (array_keys(PluginHelper::$plugins) as $addon) {
            delete_transient($this->getProductInfoCacheKey($addon, $licenseKey));
            $this->deleteRefusal($this->getRefusalCacheKey($addon, $licenseKey));
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
        $cacheKey   = $this->getProductInfoCacheKey($addonSlug, $licenseKey);
        $refusalKey = $this->getRefusalCacheKey($addonSlug, $licenseKey);

        $refusal = $this->getRefusal($refusalKey);
        if ($refusal !== false) {
            return null;
        }

        $remoteRequest = new RemoteRequest('GET', "{$this->apiUrl}/product/download", [
            'license_key' => $licenseKey,
            'domain'      => $this->requestDomain(),
            'plugin_slug' => $addonSlug,
        ]);

        try {
            // Use custom cache key for proper multisite/multilingual support
            $result = $remoteRequest->execute(true, true, DAY_IN_SECONDS, $cacheKey);

            // A licence that answers again clears whatever we were remembering about it,
            // so a customer who renews is never held back by a stale refusal.
            $this->deleteRefusal($refusalKey);

            return $result;

        } catch (Exception $e) {
            $this->rememberRefusal($refusalKey, $remoteRequest->getResponseCode());

            throw $e;
        }
    }

    /**
     * Remember that this add-on, licence and address was turned away.
     *
     * The response code is the whole decision. `RemoteRequest` throws the same plain
     * `Exception` whether WordPress could not reach the host at all or the server
     * answered "this licence expired", and treating those alike is what made a refused
     * install ask every five minutes forever.
     *
     * - **A 4xx** is the server's considered answer about the licence. Nothing we do in
     *   five minutes changes it, so it is held for twelve hours. The one exception is
     *   429, which is the server asking us to slow down — that is about the request, not
     *   the licence, so it backs off instead.
     * - **No code at all** means `wp_remote_request` returned a `WP_Error`: a timeout, a
     *   DNS failure, a refused connection. Nothing has been decided.
     * - **A 5xx** means the server is unwell. Also nothing decided.
     *
     * @param string   $refusalKey
     * @param int|null $responseCode
     *
     * @return void
     */
    private function rememberRefusal($refusalKey, $responseCode)
    {
        $code = is_numeric($responseCode) ? (int) $responseCode : 0;

        if ($code >= 400 && $code < 500 && $code !== 429) {
            $this->storeRefusal($refusalKey, self::AUTHORITATIVE_CACHE_DURATION, $code, 0);

            return;
        }

        // Transport failure, 5xx, or 429: try again, but not as often each time.
        $attempts = $this->refusalAttempts($refusalKey) + 1;

        $this->storeRefusal($refusalKey, $this->transientRetryDelay($attempts), $code, $attempts);
    }

    /**
     * How long to wait after a failure that decided nothing.
     *
     * Doubles per consecutive attempt from five minutes, capped. A site that cannot
     * reach us keeps trying, but a whole fleet that cannot reach us does not turn into a
     * fixed-rate flood the moment the server comes back.
     *
     * @param int $attempts
     *
     * @return int Seconds.
     */
    private function transientRetryDelay($attempts)
    {
        // 2 ** 10 is already far past the cap; clamping keeps the shift cheap and safe.
        $exponent = max(0, min((int) $attempts - 1, 10));
        $delay    = self::NEGATIVE_CACHE_DURATION * (2 ** $exponent);

        return (int) min($delay, self::MAX_TRANSIENT_CACHE_DURATION);
    }

    /**
     * How many times in a row this address has failed to get an answer.
     *
     * @param string $refusalKey
     *
     * @return int
     */
    private function refusalAttempts($refusalKey)
    {
        $refusal = $this->getRefusal($refusalKey);

        return is_array($refusal) && isset($refusal['attempts']) ? (int) $refusal['attempts'] : 0;
    }

    /**
     * Read a remembered refusal.
     *
     * Site transients on multisite, so a subdirectory network shares one entry rather
     * than repeating the same refused request once per subsite. The key already carries
     * the address, so a subdomain network still keeps its subsites apart.
     *
     * @param string $refusalKey
     *
     * @return array|false
     */
    private function getRefusal($refusalKey)
    {
        $refusal = is_multisite() ? get_site_transient($refusalKey) : get_transient($refusalKey);

        // Anything that is not our own shape is treated as absent rather than trusted:
        // the previous release stored an object here, and an upgrade must not trip on it.
        return is_array($refusal) && isset($refusal['code']) ? $refusal : false;
    }

    /**
     * @param string $refusalKey
     * @param int    $duration
     * @param int    $code
     * @param int    $attempts
     *
     * @return void
     */
    private function storeRefusal($refusalKey, $duration, $code, $attempts)
    {
        $refusal = [
            'code'     => (int) $code,
            'attempts' => (int) $attempts,
        ];

        if (is_multisite()) {
            set_site_transient($refusalKey, $refusal, $duration);

            return;
        }

        set_transient($refusalKey, $refusal, $duration);
    }

    /**
     * @param string $refusalKey
     *
     * @return void
     */
    private function deleteRefusal($refusalKey)
    {
        if (is_multisite()) {
            delete_site_transient($refusalKey);

            return;
        }

        delete_transient($refusalKey);
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
        if (empty($licenseKey) || !preg_match('/^[a-zA-Z0-9-]+$/', $licenseKey)) {
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
            throw new LicenseException(esc_html__('Invalid license response!', 'wp-sms'));
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

            // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Message is escaped, $status/$code are internal params
            throw new LicenseException(
                esc_html($message),
                $status,
                $code
            );
            // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped

        }

        if (!empty($product)) {
            $productSlugs = array_column($licenseData->products, 'slug');

            if (!in_array($product, $productSlugs, true)) {
                // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Product name is escaped via esc_html()
                throw new LicenseException(
                    /* translators: %s: Add-On name */
                    sprintf(__('The license is not related to the requested Add-On <b>%s</b>.', 'wp-sms'), esc_html($product))
                );
                // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }

        LicenseHelper::storeLicense($licenseKey, $licenseData);

        // Clear product info cache on successful license validation
        // This ensures fresh download URLs after license changes (renewal, domain addition, etc.)
        $this->clearProductInfoCache($licenseKey);

        return $licenseData;
    }
}
