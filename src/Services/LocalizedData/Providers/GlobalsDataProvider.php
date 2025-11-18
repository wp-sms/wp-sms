<?php

namespace WP_SMS\Services\LocalizedData\Providers;

use WP_SMS\Services\LocalizedData\DataProviderInterface;

/**
 * Globals Data Provider
 *
 * Provides global configuration data like REST API settings, nonce, etc.
 *
 * @package WP_SMS\Services\LocalizedData\Providers
 * @since   7.2
 */
class GlobalsDataProvider implements DataProviderInterface
{
    /**
     * Get the provider's unique key
     *
     * @return string
     */
    public function getKey(): string
    {
        return 'globals';
    }

    /**
     * Get globals data
     *
     * @return array
     */
    public function getData(): array
    {
        $data = [
            'nonce'                => wp_create_nonce('wp_rest'),
            'restUrl'              => esc_url_raw(rest_url('wpsms/v1/')),
            'pluginVersion'        => WP_SMS_VERSION,
            'frontend_build_url'   => plugins_url('public/react', WP_SMS_DIR . 'wp-sms.php'),
            'jsonPath'             => plugins_url('public/data', WP_SMS_DIR . 'wp-sms.php'),
            'react_starting_point' => '#settings/general',
        ];

        return apply_filters('wp_sms_localized_globals_data', $data);
    }
}
