<?php

namespace WSms\SubscriptionForm;

use WSms\Branding\BrandingRepository;
use WSms\PhoneRestriction\RestrictionSettings;

defined('ABSPATH') || exit;

class SubscriptionFormRenderer
{
    public function __construct(
        private readonly SubscriptionFormRepository $formRepository,
        private readonly RestrictionSettings $restrictionSettings,
        private readonly BrandingRepository $brandingRepo,
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
        $baseUrl = plugin_dir_url(WP_SMS_MAIN_FILE) . 'public/auth/';
        $version = defined('WP_SMS_VERSION') ? WP_SMS_VERSION : '8.0.0';

        wp_enqueue_script(
            'wsms-subscription-form',
            $baseUrl . 'subscription-form.js',
            [],
            $version,
            true,
        );

        wp_enqueue_style(
            'wsms-subscription-form',
            $baseUrl . 'subscription-form.css',
            [],
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
            'restUrl'        => rest_url('wsms/v1/'),
            'nonce'          => wp_create_nonce('wp_rest'),
        ];

        if ($primaryColor) {
            $config['primaryColor'] = sanitize_hex_color($primaryColor);
        }

        $config['phoneInput'] = $this->restrictionSettings->getPhoneInputDisplayConfig();

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
