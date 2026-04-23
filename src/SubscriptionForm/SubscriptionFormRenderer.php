<?php

namespace WSms\SubscriptionForm;

use WSms\Auth\CaptchaGuard;
use WSms\Branding\BrandingRepository;
use WSms\PhoneRestriction\RegistersVendorAsset;
use WSms\PhoneRestriction\RestrictionSettings;
use WSms\Support\EnqueuesCaptchaScript;

defined('ABSPATH') || exit;

class SubscriptionFormRenderer
{
    use RegistersVendorAsset;
    use EnqueuesCaptchaScript;

    public function __construct(
        private readonly SubscriptionFormRepository $formRepository,
        private readonly RestrictionSettings $restrictionSettings,
        private readonly BrandingRepository $brandingRepo,
        private readonly CaptchaGuard $captchaGuard,
        private readonly \WSms\Auth\SettingsRepository $settingsRepo,
    ) {
    }

    public function init(): void
    {
        add_shortcode('wsms_subscribe', [$this, 'renderShortcode']);
        $this->registerBlock();
    }

    public function renderShortcode(array $atts = []): string
    {
        $atts = shortcode_atts(['id' => ''], $atts, 'wsms_subscribe');
        $slug = sanitize_title($atts['id']);

        if (!$slug) {
            return '';
        }

        $form = $this->formRepository->findBySlug($slug);

        if (!$form || !$form->isActive()) {
            return '';
        }

        $this->enqueueAssets($form);

        return sprintf(
            '<div class="wsms-subscription-form" data-wsms-sub-form="%s"></div>',
            esc_attr($slug),
        );
    }

    private function registerBlock(): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(
            WP_SMS_DIR . 'public/blocks/subscription-form',
            [
                'render_callback' => [$this, 'renderBlock'],
            ],
        );
    }

    public function renderBlock(array $attributes): string
    {
        $slug = sanitize_title($attributes['formSlug'] ?? '');

        if (!$slug) {
            return '<p>' . esc_html__('Please select a subscription form.', 'wp-sms') . '</p>';
        }

        return $this->renderShortcode(['id' => $slug]);
    }

    private function enqueueAssets(SubscriptionForm $form): void
    {
        $baseUrl = WP_SMS_URL . 'public/auth/';
        $version = WP_SMS_VERSION;

        $this->registerVendorAsset();

        wp_enqueue_style('wsms-vendor');
        wp_enqueue_script(
            'wsms-subscription-form',
            $baseUrl . 'subscription-form.js',
            ['wsms-vendor', 'wp-api-fetch'],
            $version,
            true,
        );

        wp_set_script_translations('wsms-subscription-form', 'wp-sms', WP_SMS_DIR . 'public/languages');

        wp_enqueue_style(
            'wsms-subscription-form',
            $baseUrl . 'subscription-form.css',
            ['wsms-vendor'],
            $version,
        );

        $slug = $form->getSlug();

        // Resolve primary color: per-form override > central branding > default.
        $globalPrimary = $this->brandingRepo->get('primary_color');
        $formPrimary = $form->getAppearance()['primary_color'] ?? null;
        $primaryColor = $formPrimary ?: $globalPrimary;

        $config = [
            'fields'         => $form->getFields(),
            'buttonText'     => $form->getButtonText(),
            'doubleOptin'    => $form->requiresDoubleOptIn(),
            'optinChannel'   => $form->getOptInChannel(),
            'successMessage' => $form->getSuccessMessage(),
            'redirectUrl'    => $form->getRedirectUrl(),
        ];

        if ($primaryColor) {
            $config['primaryColor'] = sanitize_hex_color($primaryColor);
        }

        // Resolve consent config: per-form override > global settings > WP privacy page.
        $consentText = $form->getConsentText() ?? $this->settingsRepo->get('subscription_consent_text', '');
        $consentRequired = $form->isConsentRequired() ?? $this->settingsRepo->get('subscription_consent_required', false);
        $privacyUrl = $form->getPrivacyUrl() ?? $this->settingsRepo->getPrivacyUrl();

        if ($consentText !== '') {
            $config['consent'] = [
                'text'     => wp_kses_post($consentText),
                'required' => (bool) $consentRequired,
            ];
            if ($privacyUrl) {
                $config['consent']['text'] = str_replace('{privacy_url}', esc_url($privacyUrl), $config['consent']['text']);
            }
        }

        $config['phoneInput'] = $this->restrictionSettings->getPhoneInputDisplayConfig();

        $captchaConfig = $this->captchaGuard->getPublicConfig();
        if ($captchaConfig) {
            $config['captcha'] = $captchaConfig;
            $this->enqueueCaptchaScript($this->captchaGuard);
        }

        wp_add_inline_script(
            'wsms-subscription-form',
            sprintf(
                'window.wsmsSubscriptionForms=window.wsmsSubscriptionForms||{};window.wsmsSubscriptionForms[%s]=%s;',
                wp_json_encode($slug),
                wp_json_encode($config),
            ),
            'before',
        );
    }
}
