<?php

namespace WSms\Auth;

defined('ABSPATH') || exit;

class AuthRouter
{
    private ?array $settings = null;
    private ?CaptchaGuard $captchaGuard = null;

    /** @var array<string, string> Route → page title mapping. */
    private static array $routeTitles = [
        ''                => 'Account',
        'login'           => 'Sign In',
        'register'        => 'Create Account',
        'forgot-password' => 'Forgot Password',
        'reset-password'  => 'Reset Password',
        'verify'          => 'Verify',
        'verify-magic-link' => 'Magic Link',
        'verify-email'    => 'Email Verification',
        'profile'         => 'Profile',
        'change-password' => 'Change Password',
        'security'        => 'Security',
    ];

    public function setCaptchaGuard(CaptchaGuard $captchaGuard): void
    {
        $this->captchaGuard = $captchaGuard;
    }

    public function registerHooks(): void
    {
        add_action('init', [$this, 'addRewriteRules']);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_filter('template_include', [$this, 'loadTemplate']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_enqueue_scripts', [$this, 'dequeueNonPluginAssets'], 999);
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

        // Pass page title to template.
        $GLOBALS['wsmsPageTitle'] = $this->getPageTitle();

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
            'wsms-auth-style',
            $pluginUrl . 'public/auth/style.css',
            [],
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

        // Enqueue CAPTCHA provider script if enabled.
        $this->enqueueCaptchaScript();
    }

    private function enqueueCaptchaScript(): void
    {
        if (!$this->captchaGuard) {
            return;
        }

        $scriptUrl = $this->captchaGuard->getScriptUrl();

        if ($scriptUrl) {
            wp_enqueue_script(
                'wsms-captcha-provider',
                $scriptUrl,
                [],
                null,
                true,
            );
        }
    }

    /**
     * Dequeue all non-WSMS styles and scripts on auth pages for a clean standalone experience.
     */
    public function dequeueNonPluginAssets(): void
    {
        if (!get_query_var('wsms_auth_page')) {
            return;
        }

        global $wp_styles, $wp_scripts;

        $allowedStyles = ['wsms-auth-style'];
        $allowedScripts = ['wsms-auth', 'wp-hooks', 'wsms-captcha-provider'];

        if ($wp_styles instanceof \WP_Styles) {
            foreach ($wp_styles->queue as $handle) {
                if (!in_array($handle, $allowedStyles, true)) {
                    wp_dequeue_style($handle);
                }
            }
        }

        if ($wp_scripts instanceof \WP_Scripts) {
            foreach ($wp_scripts->queue as $handle) {
                if (!in_array($handle, $allowedScripts, true)) {
                    wp_dequeue_script($handle);
                }
            }
        }
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

    private function getPageTitle(): string
    {
        $route = get_query_var('wsms_auth_route', '');
        // Strip query string and trailing slash.
        $route = trim(strtok($route, '?'), '/');

        return self::$routeTitles[$route] ?? 'Account';
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
