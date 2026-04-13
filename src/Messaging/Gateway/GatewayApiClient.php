<?php

namespace WSms\Messaging\Gateway;

defined('ABSPATH') || exit;

class GatewayApiClient
{
    private const API_URL = 'https://gateways.wsms.io/v2/gateways.json';
    private const TRANSIENT_KEY = 'wsms_gateway_api_data';
    private const FALLBACK_OPTION = 'wsms_gateway_api_data_fallback';
    private const TTL = DAY_IN_SECONDS;
    private const TIMEOUT = 10;

    /** @var array<string, array>|null In-memory cache to avoid repeated transient lookups */
    private ?array $memoryCache = null;

    /**
     * Get all gateway data as a slug-keyed associative array.
     *
     * @return array<string, array>|null Null if no data is available (API unreachable AND no cache/fallback).
     */
    public function getAll(): ?array
    {
        if ($this->memoryCache !== null) {
            return $this->memoryCache;
        }

        // Try transient cache first
        $cached = get_transient(self::TRANSIENT_KEY);
        if (is_array($cached) && !empty($cached)) {
            $this->memoryCache = $cached;
            return $cached;
        }

        // Transient expired — fetch from API
        $data = $this->fetch();
        if ($data !== null) {
            $this->memoryCache = $data;
            return $data;
        }

        // Fetch failed — try stale fallback
        $fallback = get_option(self::FALLBACK_OPTION, null);
        if (is_array($fallback) && !empty($fallback)) {
            $this->memoryCache = $fallback;
            return $fallback;
        }

        return null;
    }

    /**
     * Get a single gateway's data by slug.
     */
    public function get(string $slug): ?array
    {
        return $this->getAll()[$slug] ?? null;
    }

    /**
     * Force-refresh the cache from the API.
     */
    public function refresh(): bool
    {
        $this->memoryCache = null;
        $data = $this->fetch();
        return $data !== null;
    }

    /**
     * Build the metadata array from a single gateway's API data.
     */
    public static function buildMetadata(array $api): array
    {
        return [
            'description' => $api['description'] ?? '',
            'website'     => $api['website'] ?? '',
            'icon'        => $api['branding']['logo_square'] ?? '',
            'regions'     => $api['coverage']['regions'] ?? [],
            'setup_url'   => $api['setup']['dashboard'] ?? '',
            'setup_notes' => $api['setup']['notes'] ?? [],
            'status'      => $api['status'] ?? 'active',
            'tier'        => $api['tier'] ?? 'free',
            'recommended' => $api['recommended'] ?? false,
            'branding'    => $api['branding'] ?? [],
            'coverage'    => $api['coverage'] ?? [],
        ];
    }

    /**
     * Merge API feature flags into a base feature set, restricted to known keys.
     */
    public static function mergeFeatures(array $base, array $api): array
    {
        if (isset($api['features'])) {
            $base = array_merge($base, array_intersect_key($api['features'], $base));
        }
        if (isset($api['test_connection'])) {
            $base['test_connection'] = $api['test_connection'];
        }

        return $base;
    }

    /**
     * Fetch from API, validate, cache, and return slug-keyed data.
     *
     * @return array<string, array>|null
     */
    private function fetch(): ?array
    {
        $url = defined('WSMS_GATEWAY_API_URL') ? WSMS_GATEWAY_API_URL : self::API_URL;
        $url = apply_filters('wsms_gateway_api_url', $url);

        $response = wp_remote_get($url, [
            'timeout' => self::TIMEOUT,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            error_log('[WP-SMS] Gateway API fetch failed: ' . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log("[WP-SMS] Gateway API returned HTTP {$code}");
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            error_log('[WP-SMS] Gateway API returned invalid JSON');
            return null;
        }

        // The built API wraps gateways in {"version", "generated", "gateways": [...]}
        $gateways = $decoded['gateways'] ?? $decoded;

        if (!is_array($gateways) || empty($gateways)) {
            error_log('[WP-SMS] Gateway API returned no gateways');
            return null;
        }

        // Re-key by slug for O(1) lookups
        $data = array_column($gateways, null, 'slug');

        set_transient(self::TRANSIENT_KEY, $data, self::TTL);
        update_option(self::FALLBACK_OPTION, $data, false);

        return $data;
    }
}
