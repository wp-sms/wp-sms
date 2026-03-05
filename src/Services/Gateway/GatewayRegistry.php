<?php

namespace WP_SMS\Services\Gateway;

if (!defined('ABSPATH')) {
    exit;
}

class GatewayRegistry
{
    const API_BASE_URL = 'https://gateways.wsms.io/v1';
    const CACHE_KEY_GATEWAYS = 'wpsms_gateway_registry';
    const CACHE_KEY_REGIONS = 'wpsms_gateway_regions';
    const CACHE_DURATION = 43200; // 12 hours

    /**
     * Get gateways and regions from API with local fallback
     *
     * @return array
     */
    public static function getGateways()
    {
        $cached = get_transient(self::CACHE_KEY_GATEWAYS);

        if ($cached !== false) {
            return apply_filters('wpsms_gateway_registry', $cached);
        }

        $result = self::fetchFromApi();

        if ($result !== null) {
            $result = self::appendLocalGateways($result);
            $result = self::filterPremiumGateways($result);
            set_transient(self::CACHE_KEY_GATEWAYS, $result, self::CACHE_DURATION);
            return apply_filters('wpsms_gateway_registry', $result);
        }

        $fallback = self::getLocalFallback();
        // Cache fallback for 1 hour (shorter, so we retry API sooner)
        set_transient(self::CACHE_KEY_GATEWAYS, $fallback, 3600);

        return apply_filters('wpsms_gateway_registry', $fallback);
    }

    /**
     * Fetch gateways and regions from the remote API
     *
     * @return array|null Null on failure
     */
    private static function fetchFromApi()
    {
        $gatewaysResponse = wp_remote_get(self::API_BASE_URL . '/gateways.json', [
            'timeout' => 10,
        ]);

        if (is_wp_error($gatewaysResponse) || wp_remote_retrieve_response_code($gatewaysResponse) !== 200) {
            return null;
        }

        $gatewaysBody = wp_remote_retrieve_body($gatewaysResponse);
        $gateways = json_decode($gatewaysBody, true);

        if (!is_array($gateways)) {
            return null;
        }

        // Fetch regions
        $regionsResponse = wp_remote_get(self::API_BASE_URL . '/regions.json', [
            'timeout' => 10,
        ]);

        $regions = [];
        if (!is_wp_error($regionsResponse) && wp_remote_retrieve_response_code($regionsResponse) === 200) {
            $regionsBody = wp_remote_retrieve_body($regionsResponse);
            $decoded = json_decode($regionsBody, true);
            if (is_array($decoded)) {
                $regions = $decoded;
            }
        }

        // API returns envelope: { version, generated, gateways: [...] }
        $gatewayList = isset($gateways['gateways']) && is_array($gateways['gateways'])
            ? $gateways['gateways']
            : $gateways;

        $regionList = isset($regions['regions']) && is_array($regions['regions'])
            ? $regions['regions']
            : $regions;

        return [
            'source'   => 'api',
            'gateways' => $gatewayList,
            'regions'  => $regionList,
        ];
    }

    /**
     * Build a local fallback by scanning gateway PHP files on disk
     *
     * Discovers gateways from includes/gateways/class-wpsms-gateway-*.php
     * and wp-sms-pro/includes/gateways/class-wpsms-pro-gateway-*.php.
     * Returns basic data (slug, name) without metadata.
     *
     * @return array
     */
    public static function getLocalFallback()
    {
        $gateways = [];
        $seen = [];

        // Scan core gateway files
        $corePattern = WP_SMS_DIR . 'includes/gateways/class-wpsms-gateway-*.php';
        foreach (glob($corePattern) as $file) {
            $slug = self::extractSlugFromFilename($file, 'class-wpsms-gateway-');
            if ($slug && $slug !== 'default' && !isset($seen[$slug])) {
                // Skip test gateway when WP_DEBUG is off
                if ($slug === 'test' && !(defined('WP_DEBUG') && WP_DEBUG)) {
                    continue;
                }

                $seen[$slug] = true;
                $gateways[] = self::buildFallbackEntry($slug, false);
            }
        }

        return [
            'source'   => 'local',
            'gateways' => $gateways,
            'regions'  => [],
        ];
    }

    /**
     * Extract gateway slug from a filename
     *
     * @param string $filepath Full file path
     * @param string $prefix   Filename prefix before the slug
     * @return string|null
     */
    private static function extractSlugFromFilename($filepath, $prefix)
    {
        $basename = basename($filepath, '.php');
        if (strpos($basename, $prefix) === 0) {
            return substr($basename, strlen($prefix));
        }
        return null;
    }

    /**
     * Build a minimal gateway entry for the fallback list
     *
     * @param string $slug
     * @param bool $premium
     * @return array
     */
    private static function buildFallbackEntry($slug, $premium)
    {
        // Derive a display name from the slug
        $name = str_replace(['_', '-'], ' ', $slug);
        $name = ltrim($name);
        $name = ucwords($name);

        return [
            'slug'        => $slug,
            'name'        => $name,
            'regions'     => [],
            'recommended' => false,
            'premium'     => $premium,
            'description' => '',
            'logo'        => '',
            'brand_color' => '',
            'website'     => '',
            'features'    => [],
        ];
    }

    /**
     * Append local-only gateways (custom, test) if they exist on disk but are missing from the API list
     *
     * @param array $data
     * @return array
     */
    private static function appendLocalGateways($data)
    {
        $localGateways = [
            'custom' => [
                'file'      => 'class-wpsms-gateway-custom.php',
                'name'      => esc_html__('Custom Gateway', 'wp-sms'),
                'condition' => true,
            ],
            'test' => [
                'file'      => 'class-wpsms-gateway-test.php',
                'name'      => esc_html__('Test Gateway', 'wp-sms'),
                'condition' => defined('WP_DEBUG') && WP_DEBUG,
            ],
        ];

        $existingSlugs = array_column($data['gateways'], 'slug');

        foreach ($localGateways as $slug => $config) {
            if (!$config['condition']) {
                continue;
            }

            if (!file_exists(WP_SMS_DIR . 'includes/gateways/' . $config['file'])) {
                continue;
            }

            if (in_array($slug, $existingSlugs, true)) {
                continue;
            }

            $entry         = self::buildFallbackEntry($slug, false);
            $entry['name'] = $config['name'];
            array_unshift($data['gateways'], $entry);
        }

        return $data;
    }

    /**
     * Remove premium gateways from the list
     *
     * @param array $data
     * @return array
     */
    private static function filterPremiumGateways($data)
    {
        $premiumGateways = array_values(array_filter($data['gateways'], function ($gateway) {
            return !empty($gateway['premium']);
        }));

        $data['premium_count']    = count($premiumGateways);
        $data['premium_gateways'] = $premiumGateways;
        $data['gateways']         = array_values(array_filter($data['gateways'], function ($gateway) {
            return empty($gateway['premium']);
        }));

        return $data;
    }

    /**
     * Clear cached gateway data
     */
    public static function clearCache()
    {
        delete_transient(self::CACHE_KEY_GATEWAYS);
        delete_transient(self::CACHE_KEY_REGIONS);
    }
}
