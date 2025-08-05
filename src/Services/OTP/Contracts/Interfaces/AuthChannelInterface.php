<?php

namespace WP_SMS\Services\OTP\Contracts\Interfaces;

interface AuthChannelInterface
{
    /**
     * Return a unique key to identify this channel, e.g., 'otp', 'magic_link'.
     */
    public function getKey(): string;

    /**
     * Invalidate a flow/session.
     */
    public function invalidate(string $flowId): void;

    /**
     * Check if the flow/session is still valid.
     */
    public function exists(string $flowId): bool;
}
