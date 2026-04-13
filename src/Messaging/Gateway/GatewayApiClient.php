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

    /** @var array<string, array>|null */
    private static ?array $cache = null;

    /**
     * Get a single gateway's data by slug.
     */
    public static function get(string $slug): ?array
    {
        return self::getAll()[$slug] ?? null;
    }

    /**
     * Get all gateway data as a slug-keyed associative array.
     *
     * @return array<string, array>|null Null if no data is available.
     */
    public static function getAll(): ?array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $cached = get_transient(self::TRANSIENT_KEY);
        if (is_array($cached) && !empty($cached)) {
            self::$cache = $cached;
            return $cached;
        }

        $data = self::fetch();
        if ($data !== null) {
            self::$cache = $data;
            return $data;
        }

        $fallback = get_option(self::FALLBACK_OPTION, null);
        if (is_array($fallback) && !empty($fallback)) {
            self::$cache = $fallback;
            return $fallback;
        }

        return null;
    }

    /**
     * Force-refresh the cache from the API.
     */
    public static function refresh(): bool
    {
        self::$cache = null;
        return self::fetch() !== null;
    }

    private static function fetch(): ?array
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

        $gateways = $decoded['gateways'] ?? $decoded;

        if (!is_array($gateways) || empty($gateways)) {
            error_log('[WP-SMS] Gateway API returned no gateways');
            return null;
        }

        $data = array_column($gateways, null, 'slug');

        set_transient(self::TRANSIENT_KEY, $data, self::TTL);
        update_option(self::FALLBACK_OPTION, $data, false);

        self::$cache = $data;

        return $data;
    }
}
