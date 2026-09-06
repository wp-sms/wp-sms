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

    /**
     * The licence the current call is about, so a refusal can be indexed against it.
     *
     * @var string|null
     */
    private $indexedLicenseKey;

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
     * The 4xx codes that are not an answer about the licence.
     *
     * 429 is the server asking us to slow down. A 404 is a route that has moved — rename
     * the endpoint during a deploy and every install would otherwise cache "refused" for
     * twelve hours and stay dead until tomorrow, long after the rollback. A 403 is what
     * an edge rule or a WAF returns, with no licence involved at all. 408 is a timeout
     * wearing a 4xx.
     *
     * Each of these used to heal in five minutes. They still do.
     */
    const UNDECIDED_CLIENT_CODES = [403, 404, 408, 429];

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
     * - **Subdirectory multisite** — `home_url()` carries the path, so `example.com/a`
     *   and `example.com/b` are separate entries. They must be: the server is given the
     *   full address and may answer differently for each. Each subsite still asks, but
     *   twice a day rather than 288 times.
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
        } else {
            // Clear cache for all known add-ons when no specific slug provided
            foreach (array_keys(PluginHelper::$plugins) as $addon) {
                delete_transient($this->getProductInfoCacheKey($addon, $licenseKey));
                $this->deleteRefusal($this->getRefusalCacheKey($addon, $licenseKey));
            }
        }

        // And every other address this licence was refused at. A network renews on one
        // subsite; the rest must not stay refused for the remaining twelve hours.
        $this->clearAllRefusals($licenseKey);
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

        $this->indexedLicenseKey = $licenseKey;

        $refusal = $this->getRefusal($refusalKey);
        if ($refusal !== false) {
            return null;
        }

        // The release before this one wrote its marker into the *success* key. That key
        // is unchanged, and RemoteRequest hands back whatever it finds there — so
        // without this an upgraded site is served `{_negative_cache: true}` as though it
        // were product info, with no download_url and no version on it.
        //
        // Cleared and then ignored, rather than answered with null: the marker means the
        // old code failed once, up to five minutes ago, and there is no reason to make an
        // upgraded site wait out WordPress's next update cycle to find out otherwise.
        $this->discardLegacyNegativeEntry($cacheKey);

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
     *   five minutes changes it, so it is held for twelve hours — except for the codes in
     *   {@see self::UNDECIDED_CLIENT_CODES}, which say something about the request or the
     *   route rather than the licence.
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

        if ($code >= 400 && $code < 500 && ! in_array($code, self::UNDECIDED_CLIENT_CODES, true)) {
            // An answer, even an unwelcome one, means the server is reachable — so the
            // outage history goes with it. Without this a site that timed out three
            // times and then got a clean 400 would wait 40 minutes for its next blip
            // instead of five.
            $this->forgetAttempts($refusalKey);
            $this->storeRefusal($refusalKey, self::AUTHORITATIVE_CACHE_DURATION, $code, 0);

            return;
        }

        // Transport failure, 5xx, or 429: try again, but not as often each time.
        $attempts = $this->recordAttempt($refusalKey);

        $this->storeRefusal($refusalKey, $this->transientRetryDelay($attempts), $code, $attempts);
    }

    /**
     * Count this failure, and return how many there have now been in a row.
     *
     * Kept in its own entry, outliving the wait it sets. Holding the counter inside the
     * refusal itself could not work: the refusal expiring is the only thing that lets
     * another attempt happen, so by the time we read it back it is always gone and the
     * count is always one — which made the backoff a fixed five minutes forever.
     *
     * The counter is given the longest wait plus an hour, so a site that recovers stops
     * carrying its history around, and one that does not keeps climbing.
     *
     * @param string $refusalKey
     *
     * @return int
     */
    private function recordAttempt($refusalKey)
    {
        $key      = $refusalKey . '_attempts';
        $attempts = (int) $this->readEntry($key) + 1;

        $this->writeEntry($key, $attempts, self::MAX_TRANSIENT_CACHE_DURATION + HOUR_IN_SECONDS);

        return $attempts;
    }

    /**
     * Forget the attempt history, so the next outage starts at five minutes again.
     *
     * @param string $refusalKey
     *
     * @return void
     */
    private function forgetAttempts($refusalKey)
    {
        $this->deleteEntry($refusalKey . '_attempts');
    }

    /**
     * Clear the previous release's marker out of the success cache.
     *
     * @param string $cacheKey
     *
     * @return void
     */
    private function discardLegacyNegativeEntry($cacheKey)
    {
        $cached = get_transient($cacheKey);

        if (is_object($cached) && isset($cached->_negative_cache)) {
            delete_transient($cacheKey);
        }
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
     * Read a remembered refusal.
     *
     * Site transients on multisite, so the row lives in one place rather than in every
     * subsite's own options table. That is where it is stored, not what is shared — the
     * key carries the address, and `home_url()` carries the path, so every subsite has
     * its own entry either way. See {@see getRefusalCacheKey()}.
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
        $this->writeEntry($refusalKey, [
            'code'     => (int) $code,
            'attempts' => (int) $attempts,
        ], $duration);

        $this->rememberKey($refusalKey);
    }

    /**
     * @param string $refusalKey
     *
     * @return void
     */
    private function deleteRefusal($refusalKey)
    {
        $this->deleteEntry($refusalKey);
        $this->forgetAttempts($refusalKey);
    }

    /**
     * Read one entry, network-wide on multisite.
     *
     * @param string $key
     *
     * @return mixed
     */
    private function readEntry($key)
    {
        return is_multisite() ? get_site_transient($key) : get_transient($key);
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $duration
     *
     * @return void
     */
    private function writeEntry($key, $value, $duration)
    {
        if (is_multisite()) {
            set_site_transient($key, $value, $duration);

            return;
        }

        set_transient($key, $value, $duration);
    }

    /**
     * @param string $key
     *
     * @return void
     */
    private function deleteEntry($key)
    {
        if (is_multisite()) {
            delete_site_transient($key);

            return;
        }

        delete_transient($key);
    }

    /**
     * The option that lists every refusal key written for a licence.
     *
     * @param string $licenseKey
     *
     * @return string
     */
    private function refusalIndexKey($licenseKey)
    {
        return 'wp_sms_license_refusal_index_' . md5($licenseKey);
    }

    /**
     * Note that a refusal exists under this key.
     *
     * Refusals are keyed on the address the server judged, so one licence collects one
     * per subsite and per language. Clearing only the address the customer happened to
     * renew on would leave the other thirty-nine refused for twelve hours — worse than
     * the five minutes they used to wait. This index is how {@see clearProductInfoCache()}
     * finds them all.
     *
     * @param string $refusalKey
     *
     * @return void
     */
    private function rememberKey($refusalKey)
    {
        if (! isset($this->indexedLicenseKey)) {
            return;
        }

        $indexKey = $this->refusalIndexKey($this->indexedLicenseKey);
        $stored   = $this->readEntry($indexKey);
        $index    = is_array($stored) ? $stored : [];

        if (! in_array($refusalKey, $index, true)) {
            $index[] = $refusalKey;
        }

        // Rewritten on every refusal, not only when the list changes. Setting the TTL
        // once and letting it count down while the refusals it points at were renewed
        // was the same mistake as holding the attempt counter inside the refusal: the
        // index expired first, a later refusal recreated it holding only itself, and a
        // renewal then freed one address while the rest stayed refused.
        $this->writeEntry($indexKey, $index, self::AUTHORITATIVE_CACHE_DURATION + DAY_IN_SECONDS);
    }

    /**
     * Clear every refusal recorded for a licence, whichever address recorded it.
     *
     * @param string $licenseKey
     *
     * @return void
     */
    private function clearAllRefusals($licenseKey)
    {
        $indexKey = $this->refusalIndexKey($licenseKey);

        foreach ((array) $this->readEntry($indexKey) as $refusalKey) {
            if (is_string($refusalKey)) {
                $this->deleteRefusal($refusalKey);
            }
        }

        $this->deleteEntry($indexKey);
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
