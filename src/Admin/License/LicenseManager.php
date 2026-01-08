<?php

namespace WP_SMS\Admin\License;

use Veronalabs\LicenseClient\LicenseHub;

/**
 * Centralized license management for WP-SMS.
 *
 * Handles SDK initialization and i18n configuration.
 */
class LicenseManager
{
    /**
     * Initialize the license SDK and register hooks.
     *
     * @return void
     */
    public static function init()
    {
        // Initialize SDK
        LicenseHub::init([
            'api_url'      => WP_SMS_LICENSE_HUB_API,
            'product_slug' => 'wp-sms',
            'default_page' => 'wp-sms',
            'pricing_url'  => WP_SMS_SITE . '/pricing?utm_source=wp-sms&utm_medium=link&utm_campaign=header',
        ]);

        // Register i18n filters
        add_filter('wp_sms_license_error_messages', [__CLASS__, 'translateErrorMessages']);
        add_filter('wp_sms_license_success_message', [__CLASS__, 'translateSuccessMessage'], 10, 3);
    }

    /**
     * Translate error messages for OAuth flow.
     *
     * @param array $messages Default messages
     * @return array Translated messages
     */
    public static function translateErrorMessages($messages)
    {
        return [
            'invalid_callback'        => __('Invalid callback URL.', 'wp-sms'),
            'token_generation_failed' => __('Failed to generate authentication token.', 'wp-sms'),
            'product_not_found'       => __('Product not found.', 'wp-sms'),
            'oauth_not_configured'    => __('OAuth not configured for this product.', 'wp-sms'),
            'invalid_token'           => __('Invalid or expired token.', 'wp-sms'),
            'missing_email'           => __('Token missing email.', 'wp-sms'),
            'invalid_state'           => __('Invalid state parameter. Please try again.', 'wp-sms'),
            'invalid_data'            => __('Invalid response data.', 'wp-sms'),
            'invalid_format'          => __('Invalid response format.', 'wp-sms'),
            'auth_failed'             => __('Authentication failed.', 'wp-sms'),
        ];
    }

    /**
     * Translate success message for OAuth flow.
     *
     * @param string $message Default message
     * @param string $displayName User display name
     * @param int $licensesActivated Number of licenses activated
     * @return string Translated message
     */
    public static function translateSuccessMessage($message, $displayName, $licensesActivated)
    {
        if ($licensesActivated > 0) {
            return sprintf(
                /* translators: %1$s: user name, %2$d: number of licenses */
                __('Welcome, %1$s! %2$d license(s) have been activated for this site.', 'wp-sms'),
                $displayName,
                $licensesActivated
            );
        }

        return sprintf(
            /* translators: %s: user name */
            __('Welcome, %s! You are now logged in.', 'wp-sms'),
            $displayName
        );
    }
}
