<?php

namespace WSms\Auth;

use WSms\Enums\SessionStage;
use WSms\Mfa\MfaManager;
use WP_User;
use WP_Error;

defined('ABSPATH') || exit;

class LoginGuard
{
    public function __construct(
        private PolicyEngine $policy,
        private AuthSession $session,
        private MfaManager $mfaManager,
        private SettingsRepository $settingsRepo,
        private ?AccountSuspension $suspension = null,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('authenticate', [$this, 'blockSuspendedUsers'], 98, 3);
        add_filter('authenticate', [$this, 'blockPendingUsers'], 99, 3);
        add_action('wp_login', [$this, 'enforceMfaOnWpLogin'], 10, 2);
        add_action('wp_login', [$this, 'enforceVerificationOnWpLogin'], 11, 2);
    }

    /**
     * Block suspended users from logging in via any wp_authenticate path.
     *
     * @param WP_User|WP_Error|null $user
     * @return WP_User|WP_Error|null
     */
    public function blockSuspendedUsers($user, $username, $password)
    {
        if (!$this->suspension || !($user instanceof WP_User)) {
            return $user;
        }

        $status = $this->suspension->isSuspended($user->ID);

        if ($status['suspended']) {
            return new WP_Error(
                'account_suspended',
                __(AccountSuspension::ERROR_MESSAGE, 'wp-sms')
            );
        }

        return $user;
    }

    /**
     * Block users with pending registration status from logging in via wp-login.php.
     *
     * @param \WP_User|\WP_Error|null $user
     * @return \WP_User|\WP_Error|null
     */
    public function blockPendingUsers($user, $username, $password)
    {
        if (!($user instanceof \WP_User)) {
            return $user;
        }

        $status = get_user_meta($user->ID, 'wsms_registration_status', true);
        if ($status === 'pending') {
            return new \WP_Error(
                'account_pending_verification',
                __('Your account is pending verification. Please use the login page to complete verification.', 'wp-sms')
            );
        }

        return $user;
    }

    /**
     * Enforce MFA on any non-REST WordPress login (wp-login.php, WooCommerce, etc.).
     *
     * Fires after wp_signon() succeeds. If MFA is required and the user has active
     * factors, clears the auth cookie and redirects to the plugin's MFA flow.
     */
    public function enforceMfaOnWpLogin(string $userLogin, \WP_User $user): void
    {
        if ($this->shouldSkipEnforcement()) {
            return;
        }

        if (!$this->policy->isMfaRequired($user->ID)) {
            return;
        }

        if (empty($this->mfaManager->getActiveMfaFactors($user->ID))) {
            return;
        }

        $this->interceptLogin($user->ID, 'password', SessionStage::PrimaryVerified, 'wp_mfa');
    }

    /**
     * Enforce verify-at-login on any non-REST WordPress login.
     *
     * Fires at priority 11 (after MFA at 10). If MFA already intercepted,
     * this never runs (MFA handler calls exit). If no MFA but verify_at_login
     * is enabled and user hasn't verified, redirect to WSMS verification flow.
     */
    public function enforceVerificationOnWpLogin(string $userLogin, \WP_User $user): void
    {
        if ($this->shouldSkipEnforcement()) {
            return;
        }

        if (!$this->needsLoginVerification($user->ID)) {
            return;
        }

        $this->interceptLogin($user->ID, 'password', SessionStage::VerificationPending, 'wp_verify');
    }

    /**
     * Enforce verify-at-signup on WooCommerce registrations.
     *
     * Called by WooCommerceServiceProvider (not registered here — WC-specific concern).
     *
     * @param int   $customerId        The new customer's user ID.
     * @param array $data              WC hook data (unused).
     * @param bool  $passwordGenerated WC hook flag (unused).
     */
    public function enforceVerificationOnWcRegistration(int $customerId, array $data = [], bool $passwordGenerated = false): void
    {
        if ($this->shouldSkipEnforcement()) {
            return;
        }

        if (!$this->needsSignupVerification()) {
            return;
        }

        update_user_meta($customerId, 'wsms_registration_status', 'pending');

        $this->interceptLogin($customerId, 'registration', SessionStage::RegistrationVerify, 'wp_verify');
    }

    /**
     * Check if a user needs login-time channel verification.
     */
    private function needsLoginVerification(int $userId): bool
    {
        $settings = $this->settingsRepo->all();

        if (
            !empty($settings['email']['enabled'])
            && ($settings['email']['usage'] ?? '') === 'login'
            && !empty($settings['email']['verify_at_login'])
            && empty(get_user_meta($userId, 'wsms_email_verified', true))
        ) {
            return true;
        }

        if (
            !empty($settings['phone']['enabled'])
            && ($settings['phone']['usage'] ?? '') === 'login'
            && !empty($settings['phone']['verify_at_login'])
            && empty(get_user_meta($userId, 'wsms_phone_verified', true))
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if verify-at-signup is enabled for any channel.
     */
    private function needsSignupVerification(): bool
    {
        $settings = $this->settingsRepo->all();

        return (!empty($settings['email']['enabled']) && !empty($settings['email']['verify_at_signup']))
            || (!empty($settings['phone']['enabled']) && !empty($settings['phone']['verify_at_signup']));
    }

    /**
     * Clear auth cookie, create session, and redirect to WSMS auth flow.
     */
    private function interceptLogin(int $userId, string $method, SessionStage $stage, string $queryParam): void
    {
        wp_clear_auth_cookie();
        wp_set_current_user(0);

        $token = $this->session->create($userId, $method, $stage);
        $authBaseUrl = $this->settingsRepo->get('auth_base_url', '/account');

        $this->redirect(home_url($authBaseUrl . '/login?' . $queryParam . '=' . urlencode($token)));
    }

    private function shouldSkipEnforcement(): bool
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            return true;
        }

        if (defined('WP_CLI') && WP_CLI) {
            return true;
        }

        if (wp_doing_ajax()) {
            return true;
        }

        if (defined('WSMS_ALLOW_WP_LOGIN') && WSMS_ALLOW_WP_LOGIN) {
            return true;
        }

        return false;
    }

    /**
     * Redirect and exit. Extracted for testability.
     *
     * @codeCoverageIgnore
     */
    protected function redirect(string $url): void
    {
        wp_safe_redirect($url);
        exit;
    }
}
