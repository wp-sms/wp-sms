<?php

namespace WP_SMS\Services\OTP\Delivery;

use InvalidArgumentException;
use WP_SMS\Services\OTP\Contracts\Interfaces\DeliveryChannelInterface;
use WP_SMS\Services\OTP\Delivery\Email\EmailChannel;
use WP_SMS\Services\OTP\Delivery\PhoneNumber\SmsChannel;

class DeliveryChannelManager
{
    /**
     * @var DeliveryChannelInterface[]
     */
    protected array $channels = [];


    public function __construct()
    {
        $this->channels = [
            'email' => new EmailChannel(),
            'sms'   => new SmsChannel()
        ];
    }
    /**
     * Register a channel.
     */
    public function register(DeliveryChannelInterface $channel): void
    {
        $key = $channel->getKey();

        if (isset($this->channels[$key])) {
            throw new InvalidArgumentException("Channel '{$key}' is already registered.");
        }

        $this->channels[$key] = $channel;
    }

    /**
     * Get a channel by key.
     */
    public function get(string $key): DeliveryChannelInterface
    {
        if (!isset($this->channels[$key])) {
            throw new InvalidArgumentException("Channel '{$key}' is not registered.");
        }

        return $this->channels[$key];
    }

    /**
     * Check if a channel exists.
     */
    public function has(string $key): bool
    {
        return isset($this->channels[$key]);
    }

    /**
     * Get all registered channels.
     */
    public function all(): array
    {
        return $this->channels;
    }
}
