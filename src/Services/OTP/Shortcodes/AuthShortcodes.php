<?php

namespace WP_SMS\Services\OTP\Shortcodes;

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
            'fields' => 'username,email,phone,password',
            'class' => '',
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
            'restBase' => rest_url('wpsms/v1'),
            'nonces' => [
                'auth' => wp_create_nonce('wpsms_auth'),
            ],
            'globals' => [
                'enabledFields' => $enabledFields,
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
     * Get globally enabled fields from settings
     */
    protected function getEnabledFields(): array
    {
        // Default to all enabled - can be filtered by settings
        $fields = [
            'username' => true,
            'email' => true,
            'phone' => true,
            'password' => true,
        ];

        return apply_filters('wpsms_auth_allowed_fields', $fields, []);
    }
}
