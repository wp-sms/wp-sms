<?php

namespace WSms\Migration\Adapters\Digits;

use WSms\Database\Connection;
use WSms\Migration\DetectionResult;

defined('ABSPATH') || exit;

class DigitsDetector
{
    private const PLUGIN_FILE = 'developer-flavor/digit.php';

    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function detect(): DetectionResult
    {
        $active = $this->isActive();
        $installed = $active || $this->isInstalled();
        $version = $this->getVersion();
        $userCount = $this->countDigitsUsers();
        $dataFound = $userCount > 0 || $this->hasDigitsOptions();

        $summary = [];
        if ($userCount > 0) {
            $summary[] = sprintf(
                __('%s users with phone numbers', 'wp-sms'),
                number_format_i18n($userCount),
            );
        }

        $totpCount = $this->countTotpUsers();
        if ($totpCount > 0) {
            $summary[] = sprintf(
                __('%s users with authenticator apps', 'wp-sms'),
                number_format_i18n($totpCount),
            );
        }

        if ($this->hasDigitsOptions()) {
            $summary[] = __('Plugin settings', 'wp-sms');
        }

        return new DetectionResult(
            installed: $installed,
            active: $active,
            version: $version,
            dataFound: $dataFound,
            userCount: $userCount,
            summary: $summary,
        );
    }

    public function isActive(): bool
    {
        if (function_exists('digits_version')) {
            return true;
        }

        if (function_exists('is_plugin_active') && is_plugin_active(self::PLUGIN_FILE)) {
            return true;
        }

        return false;
    }

    public function isInstalled(): bool
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        return isset($plugins[self::PLUGIN_FILE]);
    }

    public function getVersion(): ?string
    {
        if (function_exists('digits_version')) {
            return digits_version();
        }

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginFile = WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE;
        if (!file_exists($pluginFile)) {
            return null;
        }

        $data = get_plugin_data($pluginFile, false, false);
        return $data['Version'] ?: null;
    }

    public function countDigitsUsers(): int
    {
        $table = $this->db->table('usermeta');
        return (int) $this->db->getVar(
            "SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE meta_key = 'digits_phone'",
        );
    }

    public function countTotpUsers(): int
    {
        $table = $this->db->table('usermeta');
        return (int) $this->db->getVar(
            "SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE meta_key = 'digits_totp'",
        );
    }

    public function hasDigitsOptions(): bool
    {
        return get_option('dig_otp_size') !== false;
    }
}
