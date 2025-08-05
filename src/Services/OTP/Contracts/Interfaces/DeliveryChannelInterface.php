<?php

namespace WP_SMS\Services\OTP\Contracts\Interfaces;

interface DeliveryChannelInterface
{
    /**
     * Send a message (OTP, link, etc.) to the destination.
     */
    public function send(string $to, string $message, array $context = []): bool;

    /**
     * Return a unique key to identify this channel (e.g., 'sms', 'email').
     */
    public function getKey(): string;
}
