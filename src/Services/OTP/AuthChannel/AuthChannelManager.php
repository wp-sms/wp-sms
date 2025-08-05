<?php

namespace WP_SMS\Services\OTP\AuthChannel;

use InvalidArgumentException;
use WP_SMS\Services\OTP\Contracts\Interfaces\AuthChannelInterface;
use WP_SMS\Services\OTP\AuthChannel\OTP\OtpService;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;

class AuthChannelManager
{
    /**
     * @var AuthChannelInterface[]
     */
    protected array $channels = [
        'otp' => OtpService::class,
        'magic_link' => MagicLinkService::class,
    ];

    /**
     * Register a new channel.
     */
    public function register(AuthChannelInterface $channel): void
    {
        $key = $channel->getKey();

        if (isset($this->channels[$key])) {
            throw new InvalidArgumentException("Auth channel '{$key}' is already registered.");
        }

        $this->channels[$key] = $channel;
    }

    /**
     * Get a channel by its key (e.g., sms, email).
     */
    public function get(string $key): AuthChannelInterface
    {
        if (!isset($this->channels[$key])) {
            throw new InvalidArgumentException("Auth channel '{$key}' is not registered.");
        }

        return $this->channels[$key];
    }

    /**
     * Check if a channel is registered.
     */
    public function has(string $key): bool
    {
        return isset($this->channels[$key]);
    }

    /**
     * Get all registered channels.
     *
     * @return AuthChannelInterface[]
     */
    public function all(): array
    {
        return $this->channels;
    }
}
