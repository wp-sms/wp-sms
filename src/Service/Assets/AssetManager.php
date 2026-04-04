<?php

namespace WSms\Service\Assets;

use WSms\Access\AccessManager;
use WSms\Extension\ExtensionRegistry;
use WSms\Onboarding\OnboardingManager;
use WSms\PhoneRestriction\RestrictionSettings;
use WSms\Service\Admin\AdminManager;
use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

/**
 * Enqueues scripts and styles for the WSMS admin dashboard.
 *
 * Uses ViteHelper to read the build manifest and enqueue
 * the React app as an ES module (WP 6.5+) or classic script.
 *
 * @since 8.0
 */
class AssetManager
{
    public function __construct(
        private readonly RestrictionSettings $restrictionSettings,
        private readonly OnboardingManager $onboardingManager,
        private readonly AccessManager $accessManager,
        private readonly ExtensionRegistry $extensionRegistry,
    ) {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdmin']);
    }

    /**
     * Enqueue assets on WSMS admin pages only.
     *
     * @param string $hook The current admin page hook suffix.
     */
    public function enqueueAdmin(string $hook): void
    {
        if (!$this->isWsmsPage($hook)) {
            return;
        }

        $this->enqueueDashboard($hook);
    }

    /**
     * Enqueue the Vite-built React dashboard app.
     */
    private function enqueueDashboard(string $hook): void
    {
        wp_enqueue_media();
        ViteHelper::enqueueDashboard('wsms-dashboard');

        wp_print_inline_script_tag(
            'var wpSmsSettings = ' . wp_json_encode($this->getLocalizedData($hook)) . ';',
            ['id' => 'wsms-settings-data']
        );
    }

    /**
     * Build the data object exposed to JavaScript as `wpSmsSettings`.
     *
     * @return array<string, mixed>
     */
    private function getLocalizedData(string $hook): array
    {
        $currentUser = wp_get_current_user();

        $data = [
            'restUrl'           => rest_url('wsms/v1/'),
            'nonce'             => wp_create_nonce('wp_rest'),
            'version'           => WP_SMS_VERSION,
            'adminUrl'          => admin_url(),
            'isPremium'         => defined('WP_SMS_PREMIUM_FILE'),
            'roles'             => wp_list_pluck(get_editable_roles(), 'name'),
            'timezone'          => wp_timezone_string(),
            'area'              => 'unified',
            'phoneInput'        => $this->restrictionSettings->getPhoneInputDisplayConfig(),
            'currentUserHasMfa' => (bool) get_user_meta($currentUser->ID, UserMeta::MFA_ENABLED, true),
            'currentUserRoles'  => $currentUser->roles,
            'isRtl'             => is_rtl(),
            'pluginUrl'         => WP_SMS_URL,
            'onboarding'            => $this->onboardingManager->getRawState(),
            'locale'                => get_locale(),
            'capabilities'          => $this->accessManager->getCurrentUserCaps(),
            'hasExtensions'         => !empty($this->extensionRegistry->getAll()),
            'detectedIntegrations'  => $this->onboardingManager->isPending()
                ? $this->onboardingManager->getDetectedIntegrations() : [],
            'detectedMigrations'    => $this->onboardingManager->isPending()
                ? $this->onboardingManager->getDetectedMigrations() : [],
        ];

        return apply_filters('wsms_admin_settings_data', $data);
    }

    /**
     * Check whether the current admin page belongs to WSMS.
     */
    private function isWsmsPage(string $hook): bool
    {
        return str_contains($hook, AdminManager::MENU_SLUG);
    }
}
