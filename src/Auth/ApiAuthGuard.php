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
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('authenticate', [$this, 'blockApiLoginForMfaUsers'], 100, 3);
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
            'API authentication is not allowed for accounts with MFA enabled. Use an application password instead.',
        );
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
