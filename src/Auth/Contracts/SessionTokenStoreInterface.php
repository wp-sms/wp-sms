<?php

namespace WSms\Auth\Contracts;

defined('ABSPATH') || exit;

/**
 * Thin abstraction over WP_Session_Tokens.
 *
 * Exists so FreshnessManager can be unit-tested without WP globals and so we
 * keep a single spot that touches `WP_Session_Tokens::get_instance()`.
 */
interface SessionTokenStoreInterface
{
    /**
     * Return the current request's session token hash, or '' when there is none
     * (e.g. unauthenticated request, application password auth).
     */
    public function currentToken(): string;

    /**
     * Return the session entry for the given user + token, or null when missing.
     *
     * The shape mirrors `WP_Session_Tokens::get($token)` — an associative array
     * that at minimum includes `expiration` and `login`, plus anything we have
     * written via {@see update()}.
     *
     * @return array<string, mixed>|null
     */
    public function get(int $userId, string $token): ?array;

    /**
     * Merge and persist fields into the session entry.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $userId, string $token, array $data): void;
}
