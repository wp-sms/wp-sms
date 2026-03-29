<?php

namespace WSms\Migration\Adapters\Digits\Steps;

use WSms\Migration\BatchResult;
use WSms\Migration\MigrationBackupService;
use WSms\Migration\Contracts\MigrationStepInterface;
use WSms\Migration\PreviewResult;

defined('ABSPATH') || exit;

class SettingsStep implements MigrationStepInterface
{
    /**
     * Maps Digits options → WP-SMS auth_settings paths.
     */
    private const SETTINGS_MAP = [
        'dig_otp_size'            => 'phone.code_length',
        'dig_mob_otp_resend_time' => 'phone.cooldown',
        'digits_captcha_provider' => 'captcha.provider',
    ];

    /**
     * Provider-specific captcha key mappings.
     * Only the keys matching the detected provider are imported.
     */
    private const CAPTCHA_KEY_MAP = [
        'recaptcha' => [
            'digits_recaptcha_site_key'   => 'captcha.site_key',
            'digits_recaptcha_secret_key' => 'captcha.secret_key',
            'digits_recaptcha_type'       => 'captcha.type',
        ],
        'turnstile' => [
            'digits_turnstile_site_key'   => 'captcha.site_key',
            'digits_turnstile_secret_key' => 'captcha.secret_key',
        ],
        'hcaptcha' => [
            'digits_hcaptcha_site_key'    => 'captcha.site_key',
            'digits_hcaptcha_secret_key'  => 'captcha.secret_key',
        ],
    ];

    /**
     * Default values from InstallManager::activateSingleSite().
     * Used to determine if admin has customized a setting.
     */
    private const WSMS_DEFAULTS = [
        'phone.code_length' => 6,
        'phone.cooldown'    => 60,
        'captcha.provider'  => null,
        'captcha.site_key'  => null,
        'captcha.secret_key' => null,
        'captcha.type'      => null,
    ];

    public function __construct(
        private readonly MigrationBackupService $backup,
    ) {
    }

    public function getId(): string
    {
        return 'settings';
    }

    public function getLabel(): string
    {
        return __('Settings', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Map Digits plugin settings to WP-SMS equivalents.', 'wp-sms');
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function getTotalCount(): int
    {
        return $this->countMappableSettings() > 0 ? 1 : 0;
    }

    public function isApplicable(): bool
    {
        return $this->countMappableSettings() > 0;
    }

    public function preview(array $options = []): PreviewResult
    {
        $diff = $this->buildSettingsDiff();

        $importable = 0;
        $customized = 0;
        $warnings = [];

        foreach ($diff as $item) {
            if ($item['customized']) {
                $customized++;
            } else {
                $importable++;
            }
        }

        $unmappable = $this->getUnmappableSettings();
        if (!empty($unmappable)) {
            $warnings[] = sprintf(
                __('The following Digits settings have no WP-SMS equivalent: %s. You may need to configure these manually.', 'wp-sms'),
                implode(', ', $unmappable),
            );
        }

        return new PreviewResult(
            totalItems: count($diff),
            readyToMigrate: $importable,
            conflicts: $customized,
            skippable: $customized,
            invalidData: 0,
            warnings: $warnings,
        );
    }

    /**
     * Build the settings diff for UI display.
     *
     * @return array[] Each entry: {path, label, current_value, digits_value, customized, action}
     */
    public function buildSettingsDiff(): array
    {
        $wsmsSettings = get_option('wsms_auth_settings', []);
        $diff = [];

        // Build the full map: base settings + provider-specific captcha keys.
        $fullMap = self::SETTINGS_MAP;
        $detectedProvider = $this->normalizeCaptchaProvider(get_option('digits_captcha_provider') ?: '');
        if ($detectedProvider !== null && isset(self::CAPTCHA_KEY_MAP[$detectedProvider])) {
            $fullMap = array_merge($fullMap, self::CAPTCHA_KEY_MAP[$detectedProvider]);
        }

        foreach ($fullMap as $digitsOption => $wsmsPath) {
            $digitsValue = get_option($digitsOption);
            if ($digitsValue === false) {
                continue;
            }

            if ($digitsOption === 'digits_captcha_provider') {
                $digitsValue = $detectedProvider;
            }

            $currentValue = $this->getNestedValue($wsmsSettings, $wsmsPath);
            $defaultValue = self::WSMS_DEFAULTS[$wsmsPath] ?? null;
            $customized = $currentValue !== null && $currentValue !== $defaultValue;

            if ((string) $currentValue === (string) $digitsValue) {
                continue;
            }

            $diff[] = [
                'path'          => $wsmsPath,
                'label'         => $this->getSettingLabel($wsmsPath),
                'current_value' => $currentValue,
                'digits_value'  => $digitsValue,
                'customized'    => $customized,
                'action'        => $customized ? 'skip' : 'import',
            ];
        }

        return $diff;
    }

    public function migrateBatch(array $options, ?string $cursor = null): BatchResult
    {
        $dryRun = !empty($options['dry_run']);
        $selectedSettings = $options['selected_settings'] ?? null;

        $diff = $this->buildSettingsDiff();

        if ($dryRun) {
            $importable = 0;
            foreach ($diff as $item) {
                $shouldImport = $selectedSettings !== null
                    ? in_array($item['path'], $selectedSettings, true)
                    : !$item['customized'];
                if ($shouldImport) {
                    $importable++;
                }
            }

            return new BatchResult(
                processed: count($diff),
                migrated: $importable,
                skipped: count($diff) - $importable,
                failed: 0,
                conflicts: 0,
                nextCursor: null,
                done: true,
            );
        }

        $this->backup->backupSettings();

        $wsmsSettings = get_option('wsms_auth_settings', []);
        $migrated = 0;
        $skipped = 0;

        foreach ($diff as $item) {
            $shouldImport = $selectedSettings !== null
                ? in_array($item['path'], $selectedSettings, true)
                : !$item['customized'];

            if (!$shouldImport) {
                $skipped++;
                continue;
            }

            $this->setNestedValue($wsmsSettings, $item['path'], $item['digits_value']);
            $migrated++;
        }

        update_option('wsms_auth_settings', $wsmsSettings, false);

        return new BatchResult(
            processed: count($diff),
            migrated: $migrated,
            skipped: $skipped,
            failed: 0,
            conflicts: 0,
            nextCursor: null,
            done: true,
        );
    }

    /** @return string[] Digits option names that have no WP-SMS mapping */
    public function getUnmappableSettings(): array
    {
        $known = [
            'digits_login_redirect'     => __('Login redirect URL', 'wp-sms'),
            'digits_reg_redirect'       => __('Registration redirect URL', 'wp-sms'),
            'digits_logout_redirect'    => __('Logout redirect URL', 'wp-sms'),
            'digits_whatsapp_api_key'   => __('WhatsApp API key', 'wp-sms'),
            'digits_custom_css'         => __('Custom CSS', 'wp-sms'),
            'digits_login_log'          => __('Login logging', 'wp-sms'),
        ];

        $found = [];
        foreach ($known as $option => $label) {
            if (get_option($option) !== false) {
                $found[] = $label;
            }
        }

        return $found;
    }

    private function countMappableSettings(): int
    {
        $count = 0;
        foreach (array_keys(self::SETTINGS_MAP) as $option) {
            if (get_option($option) !== false) {
                $count++;
            }
        }
        return $count;
    }

    private function normalizeCaptchaProvider(string $provider): ?string
    {
        return match (strtolower(trim($provider))) {
            'recaptcha', 'recaptcha_v2', 'recaptcha_v3', 'google' => 'recaptcha',
            'turnstile', 'cloudflare' => 'turnstile',
            'hcaptcha' => 'hcaptcha',
            default => null,
        };
    }

    private function getNestedValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return null;
            }
            $data = $data[$key];
        }
        return $data;
    }

    private function setNestedValue(array &$data, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $current = &$data;
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }

    private function getSettingLabel(string $path): string
    {
        return match ($path) {
            'phone.code_length'  => __('OTP code length', 'wp-sms'),
            'phone.cooldown'     => __('OTP cooldown', 'wp-sms'),
            'captcha.provider'   => __('CAPTCHA provider', 'wp-sms'),
            'captcha.site_key'   => __('CAPTCHA site key', 'wp-sms'),
            'captcha.secret_key' => __('CAPTCHA secret key', 'wp-sms'),
            'captcha.type'       => __('CAPTCHA type', 'wp-sms'),
            default              => $path,
        };
    }
}
