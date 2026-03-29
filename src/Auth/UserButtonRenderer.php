<?php

namespace WSms\Auth;

use WSms\Branding\BrandingRepository;
use WSms\PhoneRestriction\RegistersVendorAsset;

defined('ABSPATH') || exit;

class UserButtonRenderer
{
    use RegistersVendorAsset;

    private bool $enqueued = false;

    public function __construct(
        private readonly BrandingRepository $brandingRepo,
    ) {
    }

    public function init(): void
    {
        add_shortcode('wsms_user_button', [$this, 'shortcode']);
    }

    /**
     * [wsms_user_button] shortcode — renders a user button dropdown for logged-in users.
     */
    public function shortcode(array $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $this->enqueueAssets();

        return '<span data-wsms-user-button></span>';
    }

    public function enqueueAssets(): void
    {
        if ($this->enqueued) {
            return;
        }
        $this->enqueued = true;

        $baseUrl = WP_SMS_URL . 'public/auth/';
        $version = WP_SMS_VERSION;

        $this->registerVendorAsset();

        wp_enqueue_script(
            'wsms-user-button',
            $baseUrl . 'user-button.js',
            ['wsms-vendor'],
            $version,
            true,
        );

        wp_set_script_translations('wsms-user-button', 'wp-sms', WP_SMS_DIR . 'public/languages');

        $user = wp_get_current_user();
        $branding = $this->brandingRepo->all();
        $baseAuthUrl = $branding['auth_page_url'] ?? '/account';

        wp_localize_script('wsms-user-button', 'wsmsUserButtonConfig', [
            'user' => [
                'display_name' => $user->display_name,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar_url' => get_avatar_url($user->ID, ['size' => 64]),
            ],
            'baseUrl' => rtrim($baseAuthUrl, '/'),
            'logoutUrl' => wp_logout_url(home_url()),
        ]);
    }
}
