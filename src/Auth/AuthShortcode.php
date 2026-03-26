<?php

namespace WSms\Auth;

use WSms\Branding\BrandingRepository;

defined('ABSPATH') || exit;

/**
 * [wsms_auth] shortcode — renders a trigger button (popup) or inline embed.
 *
 * Usage:
 *   [wsms_auth]                                        → "Sign In" button opening login popup
 *   [wsms_auth view="register"]                        → "Sign In" button opening register popup
 *   [wsms_auth text="Get Started"]                     → Custom button text
 *   [wsms_auth id="vendor-reg" view="register"]        → Popup with specific registration form
 *   [wsms_auth id="vendor-reg" view="register" mode="embed"]  → Inline embedded registration form
 *
 * @since 8.0
 */
class AuthShortcode
{
    private bool $enqueued = false;
    private int $embedCount = 0;

    public function __construct(
        private SettingsRepository $settingsRepo,
        private BrandingRepository $brandingRepo,
    ) {
    }

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
            'id'   => '',
            'mode' => 'popup',
        ], $atts, 'wsms_auth');

        $this->maybeEnqueueAssets();

        if ($atts['mode'] === 'embed') {
            return $this->renderEmbed($atts);
        }

        return $this->renderPopupTrigger($atts);
    }

    private function renderPopupTrigger(array $atts): string
    {
        $view = esc_attr($atts['view']);
        $text = esc_html($atts['text'] ?: 'Sign In');
        $formAttr = $atts['id'] ? sprintf(' data-wsms-form-id="%s"', esc_attr($atts['id'])) : '';

        return sprintf(
            '<div class="wp-block-button"><button type="button" data-wsms-auth-view="%s"%s class="wsms-auth-trigger wp-block-button__link wp-element-button">%s</button></div>',
            $view,
            $formAttr,
            $text,
        );
    }

    private function renderEmbed(array $atts): string
    {
        $this->embedCount++;
        $containerId = 'wsms-auth-embed-' . $this->embedCount;
        $view = esc_attr($atts['view']);
        $formSlug = esc_attr($atts['id']);

        return sprintf(
            '<div id="%s" class="wsms-auth-embed" data-wsms-embed-view="%s" data-wsms-embed-form="%s" style="outline:none"></div>',
            $containerId,
            $view,
            $formSlug,
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
            'branding'   => $this->brandingRepo->all(),
        ]);
    }

    private function getBaseUrl(): string
    {
        return $this->settingsRepo->get('auth_base_url', '/account');
    }
}
