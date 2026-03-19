<?php

namespace WSms\Verification;

use WSms\Auth\AccountManager;
use WSms\Auth\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * General re-verification for email/phone changes.
 *
 * When enabled, intercepts profile changes from:
 * - WordPress admin profile page (email)
 * - WooCommerce Edit Account (email)
 * - WooCommerce Edit Address (phone)
 *
 * Delegates to AccountManager's pending-verification flow:
 * stores new value as pending, sends OTP, blocks direct save.
 * The user confirms via the WSMS verification flow.
 */
class ProfileReverification
{
    public function __construct(
        private AccountManager $accountManager,
        private SettingsRepository $settingsRepo,
    ) {
    }

    public function registerHooks(): void
    {
        $emailReverify = !empty($this->settingsRepo->channel('email')['reverify_on_change']);
        $phoneReverify = !empty($this->settingsRepo->channel('phone')['reverify_on_change']);

        if (!$emailReverify && !$phoneReverify) {
            return;
        }

        if ($emailReverify) {
            // WordPress admin profile — intercept email change before save.
            add_filter('wp_pre_insert_user_data', [$this, 'interceptWpEmailChange'], 10, 4);

            // WooCommerce Edit Account — block email change, create pending verification.
            add_action('woocommerce_save_account_details_errors', [$this, 'interceptWcEmailChange'], 10, 2);
            add_filter('woocommerce_user_account_email_change_confirmation', '__return_false');
        }

        if ($phoneReverify) {
            // WooCommerce Edit Address — intercept phone change.
            add_action('woocommerce_after_save_address_validation', [$this, 'interceptWcPhoneChange'], 10, 4);
        }
    }

    /**
     * WordPress admin profile: intercept email change via wp_pre_insert_user_data filter.
     *
     * This filter fires before wp_update_user() writes to the DB (WP 5.8+).
     * If the email changed, we revert it in the data array and create a pending verification.
     *
     * @param array $data    User data to be inserted/updated.
     * @param bool  $update  Whether this is an update (true) or insert (false).
     * @param int|null $userId The user ID being updated, or null for insert.
     * @param array $userdata Raw user data passed to wp_insert_user/wp_update_user.
     * @return array Modified data with email reverted if re-verification needed.
     */
    public function interceptWpEmailChange(array $data, bool $update, ?int $userId, array $userdata): array
    {
        if (!$update || !$userId) {
            return $data;
        }

        $user = get_userdata($userId);
        if (!$user) {
            return $data;
        }

        $newEmail = $data['user_email'] ?? '';
        $currentEmail = $user->user_email;

        if ($newEmail === $currentEmail || empty($newEmail)) {
            return $data;
        }

        // Delegate to AccountManager which handles cooldown, duplicate checks,
        // pending meta, and OTP delivery.
        $result = $this->accountManager->updateProfile($userId, ['email' => $newEmail]);

        if (!empty($result->meta['email_verification_required'])) {
            // Revert email so WP doesn't save the unverified address.
            $data['user_email'] = $currentEmail;
        }

        return $data;
    }

    /**
     * WooCommerce Edit Account: intercept email change.
     *
     * Fires before WC saves account details. If email changed, we add an error
     * to prevent the save and create a pending verification via AccountManager.
     */
    public function interceptWcEmailChange(&$errors, &$user): void
    {
        $newEmail = sanitize_text_field(wp_unslash($_POST['account_email'] ?? ''));

        // WC already sets $user->user_email to the new value before this hook,
        // so compare against the current DB user instead.
        $currentUser = get_userdata($user->ID);
        $currentEmail = $currentUser ? $currentUser->user_email : '';

        if (empty($newEmail) || $newEmail === $currentEmail) {
            return;
        }

        $result = $this->accountManager->updateProfile($user->ID, ['email' => $newEmail]);

        if (!empty($result->meta['email_verification_required'])) {
            $verifyUrl = home_url($this->settingsRepo->get('auth_base_url', '/account') . '/profile');

            $errors->add(
                'wsms_email_reverify',
                sprintf(
                    /* translators: %s: link to verification page */
                    __('A verification code has been sent to your new email address. <a href="%s">Verify it here</a> before the change takes effect.', 'wp-sms'),
                    esc_url($verifyUrl),
                ),
            );

            // Revert both the $user object and POST so WC doesn't save the unverified address.
            $user->user_email = $currentEmail;
            $_POST['account_email'] = $currentEmail;
        } elseif (!$result->success) {
            $errors->add('wsms_email_change_failed', $result->message);
        }
    }

    /**
     * WooCommerce Edit Address: intercept phone change.
     *
     * Fires during address save validation. If billing phone changed,
     * create a pending verification via AccountManager.
     */
    public function interceptWcPhoneChange(int $userId, string $addressType, array $address, $customer): void
    {
        if ($addressType !== 'billing') {
            return;
        }

        $currentPhone = get_user_meta($userId, 'billing_phone', true);
        $newPhone = sanitize_text_field(wp_unslash($_POST['billing_phone'] ?? ''));

        if (empty($newPhone) || $newPhone === $currentPhone) {
            return;
        }

        $result = $this->accountManager->updateProfile($userId, ['phone' => $newPhone]);

        if (!empty($result->meta['phone_verification_required'])) {
            $verifyUrl = home_url($this->settingsRepo->get('auth_base_url', '/account') . '/profile');

            wc_add_notice(
                sprintf(
                    /* translators: %s: link to verification page */
                    __('A verification code has been sent to your new phone number. <a href="%s">Verify it here</a> before the change takes effect.', 'wp-sms'),
                    esc_url($verifyUrl),
                ),
                'notice',
            );
        } elseif (!$result->success) {
            wc_add_notice($result->message, 'error');
        }
    }
}
