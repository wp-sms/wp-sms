<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class AddOns
{
    private $addOns = [];
    private $error;

    public function init()
    {
        $this->prepareAddOnsFromApi();
        $this->prepareResponse();
        $this->renderPage();
    }

    public function prepareAddOnsFromApi()
    {
        $transientKey  = 'wp_sms_add_ons';
        $cacheDuration = WEEK_IN_SECONDS; // 1 week in seconds

        // Check if there's a cached response
        $cachedResponse = get_transient($transientKey);

        if ($cachedResponse !== false) {
            // Use the cached response
            $this->addOns = json_decode($cachedResponse);
            return;
        }

        // Fetch response from API
        $response = wp_remote_get(WP_SMS_SITE . '/wp-json/wc/store/products?category=204', ['timeout' => 35]);

        // Check response
        if (is_wp_error($response)) {
            $this->error = $response->get_error_message();
            return;
        }

        if (200 != wp_remote_retrieve_response_code($response)) {
            return;
        }

        // Get the body of the response
        $body = wp_remote_retrieve_body($response);

        // Decode the response and assign it
        $this->addOns = json_decode($body);

        // Store the response in a transient
        set_transient($transientKey, $body, $cacheDuration);
    }

    public function prepareResponse()
    {
        foreach ($this->addOns as $addOn) {
            $plugin                        = "{$addOn->slug}/{$addOn->slug}.php";
            $addOn->meta['activate_url']   = add_query_arg(['action' => 'activate', 'plugin' => $plugin, '_wpnonce' => wp_create_nonce("activate-plugin_{$plugin}")], admin_url('plugins.php'));
            $addOn->meta['deactivate_url'] = add_query_arg(['action' => 'deactivate', 'plugin' => $plugin, '_wpnonce' => wp_create_nonce("deactivate-plugin_{$plugin}")], admin_url('plugins.php'));

            if (is_plugin_active($plugin)) {
                $addOn->meta['status']       = 'active';
                $addOn->meta['status_label'] = esc_html__('Active', 'wp-sms');
            } else if (file_exists(WP_PLUGIN_DIR . "/{$plugin}")) {
                $addOn->meta['status']       = 'inactive';
                $addOn->meta['status_label'] = esc_html__('Inactive', 'wp-sms');
            } else {
                $addOn->meta['status']       = 'not-installed';
                $addOn->meta['status_label'] = esc_html__('Not installed', 'wp-sms');
            }
        }
    }

    public function renderPage()
    {
        $args = [
            'addOns' => $this->addOns
        ];

        echo Helper::loadTemplate('admin/add-ons.php', $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}