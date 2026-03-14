<?php

namespace WSms\Auth;

use WSms\Enums\SessionStage;
use WSms\Mfa\MfaManager;

defined('ABSPATH') || exit;

class LoginGuard
{
    public function __construct(
        private PolicyEngine $policy,
        private AuthSession $session,
        private MfaManager $mfaManager,
        private SettingsRepository $settingsRepo,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('authenticate', [$this, 'blockPendingUsers'], 99, 3);
        add_action('wp_login', [$this, 'enforceMfaOnWpLogin'], 10, 2);
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

        // MFA required + active factors: intercept the login.
        wp_clear_auth_cookie();
        wp_set_current_user(0);

        $token = $this->session->create($user->ID, 'password', SessionStage::PrimaryVerified);
        $authBaseUrl = $this->settingsRepo->get('auth_base_url', '/account');
        $url = home_url($authBaseUrl . '/login?wp_mfa=' . urlencode($token));

        $this->redirect($url);
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
