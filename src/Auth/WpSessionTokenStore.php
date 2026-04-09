<?php

namespace WSms\Auth;

use WP_Session_Tokens;
use WSms\Auth\Contracts\SessionTokenStoreInterface;

defined('ABSPATH') || exit;

/**
 * Default SessionTokenStore implementation backed by WordPress core.
 *
 * WP_Session_Tokens stores its hash keys via `hash('sha256', $token)`; when
 * we call `get()`/`update()` we must hand it the raw token (it hashes
 * internally). The value returned by `wp_get_session_token()` is the raw
 * cookie token, so no extra hashing is needed here.
 */
class WpSessionTokenStore implements SessionTokenStoreInterface
{
    public function currentToken(): string
    {
        if (!function_exists('wp_get_session_token')) {
            return '';
        }

        $token = wp_get_session_token();

        return is_string($token) ? $token : '';
    }

    public function get(int $userId, string $token): ?array
    {
        if ($token === '' || $userId <= 0) {
            return null;
        }

        $manager = WP_Session_Tokens::get_instance($userId);
        $session = $manager->get($token);

        return is_array($session) ? $session : null;
    }

    public function update(int $userId, string $token, array $data): void
    {
        if ($token === '' || $userId <= 0) {
            return;
        }

        $manager = WP_Session_Tokens::get_instance($userId);
        $existing = $manager->get($token);

        if (!is_array($existing)) {
            // No session entry — nothing to update. This can happen during
            // edge transitions (just-destroyed session); the caller will
            // observe this on the next isFresh() call and force step-up.
            return;
        }

        $manager->update($token, array_merge($existing, $data));
    }
}
