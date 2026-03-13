<?php

namespace WSms\Mfa\Contracts;

defined('ABSPATH') || exit;

/**
 * Channels that can verify a magic link token and resolve it to a user ID.
 *
 * Implemented by channels supporting magic-link-style authentication
 * (e.g., EmailChannel, PhoneChannel).
 */
interface SupportsTokenVerification
{
    /**
     * Verify a magic link token and return the associated user ID.
     *
     * @return int|null User ID on success, null on failure.
     */
    public function verifyTokenAndResolveUser(string $token): ?int;
}
