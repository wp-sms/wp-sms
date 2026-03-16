<?php

namespace WSms\Messaging\Gateway\Email;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;

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
        return [];
    }

    public function validateConfig(array $config): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return true;
    }
}
