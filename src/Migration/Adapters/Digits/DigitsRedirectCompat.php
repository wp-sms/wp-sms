<?php

namespace WSms\Migration\Adapters\Digits;

defined('ABSPATH') || exit;

class DigitsRedirectCompat
{
    private const TTL_DAYS = 90;
    private const OPTION_KEY = 'wsms_migration_compat_redirects';

    public function register(): void
    {
        $config = get_option(self::OPTION_KEY);
        if (!$config || !($config['active'] ?? false)) {
            return;
        }

        // Check TTL expiry.
        $expiresAt = $config['expires_at'] ?? '';
        if ($expiresAt !== '' && strtotime($expiresAt) < time()) {
            delete_option(self::OPTION_KEY);
            return;
        }

        $redirects = $config['redirects'] ?? [];
        if (empty($redirects)) {
            return;
        }

        add_action('template_redirect', function () use ($redirects) {
            $currentPath = trim(wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

            foreach ($redirects as $redirect) {
                $fromPath = trim($redirect['from'] ?? '', '/');
                if ($fromPath !== '' && $currentPath === $fromPath) {
                    wp_redirect($redirect['to'], 301);
                    exit;
                }
            }
        }, 1);
    }

    /**
     * Build redirect mappings from Digits pages to WP-SMS equivalents.
     */
    public function buildRedirects(string $authBaseUrl): array
    {
        $redirects = [];

        // Digits login/register page from settings.
        $digitsLoginPageId = get_option('digits_manage_account_page_id');
        if ($digitsLoginPageId) {
            $permalink = get_permalink($digitsLoginPageId);
            if ($permalink) {
                $fromPath = trim(wp_parse_url($permalink, PHP_URL_PATH), '/');
                $redirects[] = [
                    'from'  => $fromPath,
                    'to'    => home_url($authBaseUrl . '/login'),
                    'label' => __('Digits login page', 'wp-sms'),
                ];
            }
        }

        return $redirects;
    }

    public function activate(string $authBaseUrl): void
    {
        $redirects = $this->buildRedirects($authBaseUrl);
        $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+' . self::TTL_DAYS . ' days'));

        update_option(self::OPTION_KEY, [
            'active'       => true,
            'activated_at' => current_time('mysql', true),
            'expires_at'   => $expiresAt,
            'redirects'    => $redirects,
        ], false);
    }

    public function deactivate(): void
    {
        delete_option(self::OPTION_KEY);
    }

    public function getRedirects(): array
    {
        $config = get_option(self::OPTION_KEY);
        return $config['redirects'] ?? [];
    }
}
