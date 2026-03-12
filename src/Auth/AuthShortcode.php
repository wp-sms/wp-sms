<?php

namespace WSms\Auth;

defined('ABSPATH') || exit;

/**
 * [wsms_auth] shortcode — renders a trigger button that opens the auth popup.
 *
 * Usage:
 *   [wsms_auth]                             → "Sign In" button opening login popup
 *   [wsms_auth view="register"]             → "Sign In" button opening register popup
 *   [wsms_auth text="Get Started"]          → Custom button text
 *   [wsms_auth view="login" text="Log In"]  → Custom view + text
 *
 * @since 8.0
 */
class AuthShortcode
{
    private bool $enqueued = false;
    private ?array $settings = null;

    public function registerHooks(): void
    {
        add_shortcode('wsms_auth', [$this, 'render']);
    }

    /**
     * @param array|string $atts Shortcode attributes.
     */
    public function render($atts): string
    {
        $atts = shortcode_atts([
            'view' => 'login',
            'text' => 'Sign In',
        ], $atts, 'wsms_auth');

        $this->maybeEnqueueAssets();

        $view = esc_attr($atts['view']);
        $text = esc_html($atts['text']);

        return sprintf(
            '<button type="button" data-wsms-auth-view="%s" class="wsms-auth-trigger">%s</button>',
            $view,
            $text,
        );
    }

    private function maybeEnqueueAssets(): void
    {
        if ($this->enqueued) {
            return;
        }

        $this->enqueued = true;

        $pluginUrl = plugin_dir_url(dirname(__DIR__, 1) . '/../wp-sms.php');
        $version   = defined('WP_SMS_VERSION') ? WP_SMS_VERSION : '8.0';

        wp_enqueue_script(
            'wsms-auth-popup',
            $pluginUrl . 'public/auth/popup.js',
            [],
            $version,
            true,
        );

        wp_localize_script('wsms-auth-popup', 'wsmsAuth', [
            'restUrl'    => rest_url('wsms/v1/'),
            'nonce'      => wp_create_nonce('wp_rest'),
            'baseUrl'    => '/' . ltrim($this->getBaseUrl(), '/'),
            'isLoggedIn' => is_user_logged_in(),
        ]);
    }

    private function getBaseUrl(): string
    {
        $this->settings ??= get_option('wsms_auth_settings', []);

        return $this->settings['auth_base_url'] ?? '/account';
    }
}
