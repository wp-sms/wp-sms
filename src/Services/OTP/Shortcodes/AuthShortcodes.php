<?php

namespace WP_SMS\Services\OTP\Shortcodes;

use WP_SMS\Services\OTP\OTPChannelHelper;

/**
 * Authentication Shortcodes
 *
 * Registers shortcodes for login, register, and combined auth forms.
 */
class AuthShortcodes
{
    /**
     * Initialize shortcodes
     */
    public function __construct()
    {
    }

    /**
     * Initialize the service
     */
    public function init(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
    }

    /**
     * Register all authentication shortcodes
     */
    public function registerShortcodes(): void
    {
        add_shortcode('wpsms_login_form', [$this, 'renderLoginForm']);
        add_shortcode('wpsms_register_form', [$this, 'renderRegisterForm']);
        add_shortcode('wpsms_auth_form', [$this, 'renderAuthForm']);
    }

    /**
     * Render login form shortcode
     */
    public function renderLoginForm($atts): string
    {
        $atts = shortcode_atts([
            'redirect' => '/',
            'methods' => 'password,otp,magic',
            'tabs' => 'false',
            'default_tab' => 'login',
            'fields' => 'username,email,phone,password',
            'class' => '',
            'mfa' => 'true',
        ], $atts, 'wpsms_login_form');

        $this->enqueueAssets();
        return $this->renderContainer($atts, 'login');
    }

    /**
     * Render register form shortcode
     */
    public function renderRegisterForm($atts): string
    {
        $atts = shortcode_atts([
            'redirect' => '/',
            'methods' => 'password,otp,magic',
            'tabs' => 'false',
            'default_tab' => 'register',
            'fields' => 'email,phone,password',
            'class' => '',
            'mfa' => 'false',
        ], $atts, 'wpsms_register_form');

        $this->enqueueAssets();
        return $this->renderContainer($atts, 'register');
    }

    /**
     * Render combined auth form shortcode
     */
    public function renderAuthForm($atts): string
    {
        $atts = shortcode_atts([
            'redirect' => '/',
            'methods' => 'password,otp,magic',
            'tabs' => 'true',
            'default_tab' => 'login',
            'fields' => 'username,email,phone,password',
            'class' => '',
            'mfa' => 'true',
        ], $atts, 'wpsms_auth_form');

        $this->enqueueAssets();
        return $this->renderContainer($atts, 'auth');
    }

    /**
     * Enqueue required assets
     */
    protected function enqueueAssets(): void
    {
        wp_enqueue_script('wp-sms-auth-form', WP_SMS_FRONTEND_BUILD_URL . 'legacy/auth-form.js', [], WP_SMS_VERSION, true);
        wp_enqueue_style('wp-sms-auth-form', WP_SMS_FRONTEND_BUILD_URL . 'legacy/assets/auth-styles.css', [], WP_SMS_VERSION);
    }

    /**
     * Render the auth container with data props
     */
    protected function renderContainer(array $atts, string $mode): string
    {
        $methods = array_filter(array_map('trim', explode(',', $atts['methods'])));
        $fields = array_filter(array_map('trim', explode(',', $atts['fields'])));
        
        // Get global field settings
        $enabledFields = $this->getEnabledFields();
        
        // Intersect with requested fields
        $enabledFields = array_intersect_key($enabledFields, array_flip($fields));
        
        $props = [
            'mode' => $mode,
            'redirect' => $atts['redirect'],
            'methods' => $methods,
            'tabs' => filter_var($atts['tabs'], FILTER_VALIDATE_BOOLEAN),
            'default_tab' => $atts['default_tab'],
            'fields' => $fields,
            'class' => $atts['class'],
            'mfa' => filter_var($atts['mfa'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'restBase' => rest_url('wpsms/v1'),
            'nonces' => [
                'auth' => wp_create_nonce('wpsms_auth'),
            ],
            'globals' => [
                'enabledFields' => $enabledFields,
                'channelSettings' => $this->getChannelSettings(),
                'mfaChannels' => $this->getMfaChannels(),
            ],
        ];

        $class = 'wpsms-auth';
        if (!empty($atts['class'])) {
            $class .= ' ' . esc_attr($atts['class']);
        }

        return sprintf(
            '<div class="%s" data-props=\'%s\'></div>',
            esc_attr($class),
            wp_json_encode($props)
        );
    }

    /**
     * Get globally enabled fields from OTP channel settings
     */
    protected function getEnabledFields(): array
    {
        // Get enabled fields from OTP channel settings
        $fields = [
            'username' => OTPChannelHelper::isChannelEnabled('username'),
            'email' => OTPChannelHelper::isChannelEnabled('email'),
            'phone' => OTPChannelHelper::isChannelEnabled('phone'),
            'password' => OTPChannelHelper::isChannelEnabled('password'),
        ];

        return apply_filters('wpsms_auth_allowed_fields', $fields, []);
    }

    /**
     * Get channel settings for frontend configuration
     */
    protected function getChannelSettings(): array
    {
        return [
            'username' => OTPChannelHelper::getChannelSettings('username'),
            'password' => OTPChannelHelper::getChannelSettings('password'),
            'phone' => OTPChannelHelper::getChannelSettings('phone'),
            'email' => OTPChannelHelper::getChannelSettings('email'),
        ];
    }

    /**
     * Get MFA channels for frontend configuration
     */
    protected function getMfaChannels(): array
    {
        return OTPChannelHelper::getMfaChannels();
    }
}
