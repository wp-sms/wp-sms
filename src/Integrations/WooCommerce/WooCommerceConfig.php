<?php

namespace WSms\Integrations\WooCommerce;

use WSms\Auth\SettingsRepository;
use WSms\Verification\VerificationConfig;

defined('ABSPATH') || exit;

class WooCommerceConfig
{
    private ?array $config = null;

    public function __construct(
        private VerificationConfig $verificationConfig,
        private SettingsRepository $settingsRepo,
    ) {
    }

    public function isCheckoutEmailEnabled(): bool
    {
        return $this->get('verify_email_at_checkout') && $this->verificationConfig->isChannelEnabled('email');
    }

    public function isCheckoutPhoneEnabled(): bool
    {
        return $this->get('verify_phone_at_checkout') && $this->verificationConfig->isChannelEnabled('phone');
    }

    public function isAuthRedirectEnabled(): bool
    {
        return (bool) $this->get('redirect_auth');
    }

    public function shouldSkipVerifiedUsers(): bool
    {
        return (bool) $this->get('skip_verified_users');
    }

    public function hasAnyCheckoutEnabled(): bool
    {
        return $this->isCheckoutEmailEnabled() || $this->isCheckoutPhoneEnabled();
    }

    /**
     * Check if checkout verification can be skipped for the current user's billing value.
     *
     * Skip only when the billing value matches the user's verified account value.
     * This prevents skipping when a user enters a different billing email/phone.
     */
    public function shouldSkipForBillingValue(string $channel, string $billingValue): bool
    {
        $accountValue = $this->getVerifiedAccountValue($channel);

        return $accountValue !== null && strtolower($billingValue) === strtolower($accountValue);
    }

    /**
     * Get the verified account email or phone for the current user.
     * Returns null if not logged in, not verified, or skip is disabled.
     */
    public function getVerifiedAccountValue(string $channel): ?string
    {
        if (!$this->shouldSkipVerifiedUsers() || !is_user_logged_in()) {
            return null;
        }

        $userId = get_current_user_id();

        if (empty(get_user_meta($userId, 'wsms_' . $channel . '_verified', true))) {
            return null;
        }

        return $this->getVerifiedValue($userId, $channel);
    }

    private function getVerifiedValue(int $userId, string $channel): ?string
    {
        if ($channel === 'email') {
            $user = get_userdata($userId);
            return $user ? strtolower($user->user_email) : null;
        }

        if ($channel === 'phone') {
            // Use wsms_phone (the WSMS-verified phone), not billing_phone
            // (which is WooCommerce-specific and may differ).
            $phone = get_user_meta($userId, 'wsms_phone', true);
            return !empty($phone) ? $phone : null;
        }

        return null;
    }

    private function get(string $key): bool
    {
        if ($this->config === null) {
            $this->config = $this->settingsRepo->channel('woocommerce');
        }

        return !empty($this->config[$key]);
    }
}
