<?php

namespace WP_SMS\Services\OTP\Assets;

/**
 * Authentication Assets Manager
 *
 * Handles global enqueuing of authentication assets that need to be available
 * on all pages (like modal JavaScript for triggers).
 */
class AuthAssets
{
    /**
     * Initialize the service
     */
    public function init(): void
    {
        // Enqueue modal JS globally since it needs to handle triggers from anywhere
        add_action('wp_enqueue_scripts', [$this, 'enqueueGlobalAssets']);
        
        // Also enqueue on admin pages for potential admin usage
        add_action('admin_enqueue_scripts', [$this, 'enqueueGlobalAssets']);
    }

    /**
     * Enqueue global authentication assets
     */
    public function enqueueGlobalAssets(): void
    {
        // Modal JavaScript - needs to be available everywhere for modal triggers
        wp_enqueue_script(
            'wp-sms-auth-modal', 
            WP_SMS_FRONTEND_BUILD_URL . 'legacy/auth-modal.js', 
            ['wp-sms-auth-form'], // Depends on auth-form.js
            WP_SMS_VERSION, 
            true
        );

        // Auth form JavaScript - needed as dependency for modal
        wp_enqueue_script(
            'wp-sms-auth-form', 
            WP_SMS_FRONTEND_BUILD_URL . 'legacy/auth-form.js', 
            [], 
            WP_SMS_VERSION, 
            true
        );

        // Auth form CSS - needed for modal styling
        wp_enqueue_style(
            'wp-sms-auth-form', 
            WP_SMS_FRONTEND_BUILD_URL . 'legacy/assets/auth-styles.css', 
            [], 
            WP_SMS_VERSION
        );

        // Localize script with necessary data
        wp_localize_script('wp-sms-auth-modal', 'wpsmsAuthData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('wpsms/v1/'),
            'nonce' => wp_create_nonce('wpsms_auth'),
            'strings' => [
                'loading' => __('Loading...', 'wp-sms'),
                'error' => __('An error occurred.', 'wp-sms'),
                'close' => __('Close', 'wp-sms'),
            ]
        ]);

        // Also add wpApiSettings for compatibility
        wp_localize_script('wp-sms-auth-modal', 'wpApiSettings', [
            'nonce' => wp_create_nonce('wp_rest'),
            'root' => esc_url_raw(rest_url()),
        ]);
    }
}
