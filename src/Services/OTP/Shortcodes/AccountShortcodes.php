<?php

namespace WP_SMS\Services\OTP\Shortcodes;

/**
 * Account Profile & MFA Shortcodes
 *
 * Provides front-end account management with profile editing and MFA enrollment.
 */
class AccountShortcodes
{
    /**
     * Initialize shortcodes
     */
    public function init(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'maybeEnqueueAssets']);
    }

    /**
     * Register all account shortcodes
     */
    public function registerShortcodes(): void
    {
        add_shortcode('wpsms_account', [$this, 'renderAccount']);
        add_shortcode('wpsms_account_profile', [$this, 'renderProfile']);
        add_shortcode('wpsms_account_mfa', [$this, 'renderMfa']);
    }

    /**
     * Render full account page with tabs
     */
    public function renderAccount($atts): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your account.', 'wp-sms') . '</p>';
        }

        $atts = shortcode_atts([
            'default_tab' => 'profile',
            'show_tabs' => 'true',
        ], $atts, 'wpsms_account');

        $this->enqueueAssets();

        ob_start();
        include WP_SMS_DIR . 'src/Services/OTP/Templates/account-page.php';
        return ob_get_clean();
    }

    /**
     * Render profile tab only
     */
    public function renderProfile($atts): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your profile.', 'wp-sms') . '</p>';
        }

        $this->enqueueAssets();

        ob_start();
        include WP_SMS_DIR . 'src/Services/OTP/Templates/account-profile.php';
        return ob_get_clean();
    }

    /**
     * Render MFA tab only
     */
    public function renderMfa($atts): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to manage MFA.', 'wp-sms') . '</p>';
        }

        $this->enqueueAssets();

        ob_start();
        include WP_SMS_DIR . 'src/Services/OTP/Templates/account-mfa.php';
        return ob_get_clean();
    }

    /**
     * Enqueue assets
     */
    private function enqueueAssets(): void
    {
        wp_enqueue_style(
            'wpsms-account',
            WP_SMS_URL . 'assets/css/account.css',
            [],
            WP_SMS_VERSION
        );

        wp_enqueue_script(
            'wpsms-account',
            WP_SMS_URL . 'assets/js/account.js',
            [],
            WP_SMS_VERSION,
            true
        );

        wp_localize_script('wpsms-account', 'wpSmsAccount', [
            'nonce' => wp_create_nonce('wp_rest'),
            'restUrl' => rest_url('wpsms/v1/'),
            'userId' => get_current_user_id(),
            'i18n' => [
                'loading' => __('Loading...', 'wp-sms'),
                'save' => __('Save Changes', 'wp-sms'),
                'cancel' => __('Cancel', 'wp-sms'),
                'verify' => __('Verify', 'wp-sms'),
                'remove' => __('Remove', 'wp-sms'),
                'add' => __('Add', 'wp-sms'),
                'verified' => __('Verified', 'wp-sms'),
                'unverified' => __('Unverified', 'wp-sms'),
                'codeSent' => __('Verification code sent', 'wp-sms'),
                'invalidCode' => __('Invalid verification code', 'wp-sms'),
                'error' => __('An error occurred', 'wp-sms'),
                'success' => __('Changes saved successfully', 'wp-sms'),
                'confirmRemove' => __('Are you sure you want to remove this MFA factor?', 'wp-sms'),
                'comingSoon' => __('Coming Soon', 'wp-sms'),
            ]
        ]);
    }

    /**
     * Maybe enqueue assets (for shortcode detection)
     */
    public function maybeEnqueueAssets(): void
    {
        global $post;
        
        if (!$post) {
            return;
        }

        if (has_shortcode($post->post_content, 'wpsms_account') ||
            has_shortcode($post->post_content, 'wpsms_account_profile') ||
            has_shortcode($post->post_content, 'wpsms_account_mfa')) {
            $this->enqueueAssets();
        }
    }
}

