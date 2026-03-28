<?php

namespace WSms\MessagingButton;

use WSms\Auth\CaptchaGuard;
use WSms\Branding\BrandingRepository;
use WSms\PhoneRestriction\RegistersVendorAsset;
use WSms\PhoneRestriction\RestrictionSettings;
use WSms\Support\EnqueuesCaptchaScript;
use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

class MessagingButtonRenderer
{
    use RegistersVendorAsset;
    use EnqueuesCaptchaScript;

    public function __construct(
        private readonly MessagingButtonSettings $settings,
        private readonly DisplayRuleEvaluator $displayRules,
        private readonly RestrictionSettings $restrictionSettings,
        private readonly BrandingRepository $brandingRepo,
        private readonly CaptchaGuard $captchaGuard,
    ) {
    }

    public function init(): void
    {
        // Bail early so we never register the wp_footer hook when the
        // widget is disabled — avoids per-page callback dispatch overhead.
        if (!$this->settings->isEnabled()) {
            return;
        }

        add_action('wp_footer', [$this, 'render']);
    }

    public function render(): void
    {
        $rules = $this->settings->get('display_rules', []);

        if (!$this->displayRules->shouldDisplay($rules)) {
            return;
        }

        $this->enqueueAssets();
    }

    private function enqueueAssets(): void
    {
        $baseUrl = plugin_dir_url(WP_SMS_MAIN_FILE) . 'public/auth/';
        $version = defined('WP_SMS_VERSION') ? WP_SMS_VERSION : '8.0.0';

        $this->registerVendorAsset();

        wp_enqueue_script(
            'wsms-messaging-button',
            $baseUrl . 'messaging-button.js',
            ['wsms-vendor'],
            $version,
            true,
        );

        wp_set_script_translations('wsms-messaging-button', 'wp-sms', WP_SMS_DIR . 'public/languages');

        $branding = $this->brandingRepo->all();

        $scriptData = [
            'restUrl' => rest_url('wsms/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'config' => $this->settings->getPublicConfig(),
            'centralPrimaryColor' => $branding['primary_color'],
            'centralColorMode' => $branding['color_mode'],
        ];

        $scriptData['phoneInput'] = $this->restrictionSettings->getPhoneInputDisplayConfig('messaging');

        $captchaConfig = $this->captchaGuard->getPublicConfig();
        if ($captchaConfig) {
            $scriptData['captcha'] = $captchaConfig;
            $this->enqueueCaptchaScript($this->captchaGuard);
        }

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $name = UserMeta::displayName($user);
            $phone = get_user_meta($user->ID, UserMeta::PHONE, true);
            $scriptData['currentUser'] = array_filter([
                'name' => $name,
                'email' => $user->user_email,
                'phone' => $phone,
            ]);
        }

        wp_add_inline_script(
            'wsms-messaging-button',
            'window.wsmsMessagingButtonConfig=' . wp_json_encode($scriptData) . ';',
            'before',
        );
    }
}
