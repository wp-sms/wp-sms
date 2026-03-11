<?php

namespace WSms\Auth;

defined('ABSPATH') || exit;

class AuthRouter
{
    private ?array $settings = null;

    public function registerHooks(): void
    {
        add_action('init', [$this, 'addRewriteRules']);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_filter('template_include', [$this, 'loadTemplate']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('login_init', [$this, 'maybeRedirectLogin']);
    }

    public function addRewriteRules(): void
    {
        $base = $this->getBaseUrl();

        add_rewrite_rule(
            '^' . ltrim($base, '/') . '/?(.*)$',
            'index.php?wsms_auth_page=1&wsms_auth_route=$matches[1]',
            'top',
        );

        // Flush once after activation.
        if (get_transient('wsms_flush_rewrite')) {
            flush_rewrite_rules(false);
            delete_transient('wsms_flush_rewrite');
        }
    }

    /**
     * @param string[] $vars
     * @return string[]
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = 'wsms_auth_page';
        $vars[] = 'wsms_auth_route';

        return $vars;
    }

    public function loadTemplate(string $template): string
    {
        if (!get_query_var('wsms_auth_page')) {
            return $template;
        }

        return dirname(__DIR__, 2) . '/views/auth/app.php';
    }

    public function enqueueAssets(): void
    {
        if (!get_query_var('wsms_auth_page')) {
            return;
        }

        $pluginUrl = plugin_dir_url(dirname(__DIR__, 1) . '/../wp-sms.php');
        $version = defined('WP_SMS_VERSION') ? WP_SMS_VERSION : '8.0';

        wp_enqueue_style(
            'wsms-auth-font',
            'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap',
            [],
            null,
        );

        wp_enqueue_style(
            'wsms-auth-style',
            $pluginUrl . 'public/auth/style.css',
            ['wsms-auth-font'],
            $version,
        );

        wp_enqueue_script(
            'wsms-auth',
            $pluginUrl . 'public/auth/app.js',
            [],
            $version,
            true,
        );

        wp_localize_script('wsms-auth', 'wsmsAuth', [
            'restUrl'    => rest_url('wsms/v1/'),
            'nonce'      => wp_create_nonce('wp_rest'),
            'baseUrl'    => '/' . ltrim($this->getBaseUrl(), '/'),
            'isLoggedIn' => is_user_logged_in(),
            'route'      => get_query_var('wsms_auth_route', ''),
        ]);
    }

    public function maybeRedirectLogin(): void
    {
        $settings = $this->getSettings();

        if (empty($settings['redirect_login'])) {
            return;
        }

        $loginUrl = home_url($this->getBaseUrl() . '/login');

        wp_redirect($loginUrl);
        exit;
    }

    private function getBaseUrl(): string
    {
        $settings = $this->getSettings();

        return $settings['auth_base_url'] ?? '/account';
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        return $this->settings ??= get_option('wsms_auth_settings', []);
    }
}
