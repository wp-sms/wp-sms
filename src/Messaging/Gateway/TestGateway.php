<?php

namespace WSms\Messaging\Gateway;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;

defined('ABSPATH') || exit;

class TestGateway implements GatewayInterface
{
    /** @var MessageInterface[] */
    public static array $sent = [];

    public function getId(): string
    {
        return 'test';
    }

    public function getName(): string
    {
        return 'Test Gateway';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'email', 'whatsapp', 'telegram'];
    }

    private const MAX_STORED = 100;

    public function send(MessageInterface $message): DeliveryResult
    {
        self::$sent[] = $message;
        if (count(self::$sent) > self::MAX_STORED) {
            self::$sent = array_slice(self::$sent, -self::MAX_STORED);
        }
        return DeliveryResult::sent('test-' . uniqid());
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [],
            'channels' => [],
        ];
    }

    public function validateConfig(array $config): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function isConfiguredForChannel(string $channel): bool
    {
        return in_array($channel, $this->getSupportedChannels());
    }

    public function getMetadata(): array
    {
        return [
            'description' => 'Test gateway for development — stores messages in memory instead of sending',
        ];
    }

    public function getFeatures(): array
    {
        return [
            'mms'              => true,
            'flash_sms'        => false,
            'delivery_receipt' => false,
            'incoming'         => false,
            'unicode'          => true,
        ];
    }

    public function getCredit(): ?string
    {
        return '999';
    }

    public static function reset(): void
    {
        self::$sent = [];
    }

    public static function lastMessage(): ?MessageInterface
    {
        return end(self::$sent) ?: null;
    }

    public static function sentCount(): int
    {
        return count(self::$sent);
    }
}
