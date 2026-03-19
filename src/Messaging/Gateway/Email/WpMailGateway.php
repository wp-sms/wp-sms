<?php

namespace WSms\Messaging\Gateway\Email;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;

defined('ABSPATH') || exit;

class WpMailGateway implements GatewayInterface
{
    public function getId(): string
    {
        return 'wp_mail';
    }

    public function getName(): string
    {
        return __('WordPress Mail', 'wp-sms');
    }

    public function getSupportedChannels(): array
    {
        return ['email'];
    }

    public function send(MessageInterface $message): DeliveryResult
    {
        $meta = $message->getMeta();
        $subject = $meta['subject'] ?? '';
        $headers = $meta['headers'] ?? ['Content-Type: text/html; charset=UTF-8'];

        $sent = wp_mail($message->getRecipient(), $subject, $message->getBody(), $headers);

        if (!$sent) {
            return DeliveryResult::failed(__('wp_mail() returned false', 'wp-sms'));
        }

        return DeliveryResult::sent();
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
        return $channel === 'email';
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Built-in WordPress mail function (wp_mail)', 'wp-sms'),
        ];
    }

    public function getFeatures(): array
    {
        return ['unicode' => true];
    }

    public function getCredit(): ?string
    {
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        return TestConnectionResult::error(__('Connection testing is not supported for this gateway', 'wp-sms'));
    }
}
