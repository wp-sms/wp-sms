<?php

namespace WSms\Auth;

use WP_Error;
use WP_User;
use WSms\Mfa\MfaManager;

defined('ABSPATH') || exit;

/**
 * Blocks REST API and XML-RPC authentication for users with active MFA factors,
 * unless they are using WordPress Application Passwords.
 *
 * This prevents attackers from bypassing MFA by authenticating directly via the API.
 *
 * @since 8.0
 */
class ApiAuthGuard
{
    public function __construct(
        private MfaManager $mfaManager,
        private ?AccountSuspension $suspension = null,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('authenticate', [$this, 'blockApiLoginForMfaUsers'], 100, 3);
        add_filter('rest_pre_dispatch', [$this, 'blockSuspendedUsersFromApi'], 10, 3);
    }

    /**
     * @param WP_User|WP_Error|null $user
     */
    public function blockApiLoginForMfaUsers($user, string $username = '', string $password = '')
    {
        if (!$user instanceof WP_User) {
            return $user;
        }

        if (!$this->isApiRequest()) {
            return $user;
        }

        if ($this->isApplicationPasswordRequest()) {
            return $user;
        }

        if (!$this->mfaManager->hasActiveFactors($user->ID)) {
            return $user;
        }

        /** @var bool $allowed */
        $allowed = apply_filters('wsms_user_api_login_enable', false, $user);

        if ($allowed) {
            return $user;
        }

        return new WP_Error(
            'mfa_api_blocked',
            __('API authentication is not allowed for accounts with MFA enabled. Use an application password instead.', 'wp-sms'),
        );
    }

    /**
     * Block suspended users authenticated via Application Passwords (rest_pre_dispatch).
     *
     * @param mixed $result
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     * @return mixed|WP_Error
     */
    public function blockSuspendedUsersFromApi($result, $server, $request)
    {
        if ($result !== null || !$this->suspension) {
            return $result;
        }

        $userId = get_current_user_id();

        if ($userId === 0) {
            return $result;
        }

        $status = $this->suspension->isSuspended($userId);

        if ($status['suspended']) {
            return new WP_Error(
                'account_suspended',
                AccountSuspension::errorMessage(),
                ['status' => 403],
            );
        }

        return $result;
    }

    private function isApiRequest(): bool
    {
        return (defined('REST_REQUEST') && REST_REQUEST)
            || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST);
    }

    private function isApplicationPasswordRequest(): bool
    {
        return did_action('application_password_did_authenticate') > 0;
    }
}
