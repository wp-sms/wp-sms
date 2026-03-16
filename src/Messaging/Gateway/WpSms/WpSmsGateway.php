<?php

namespace WSms\Messaging\Gateway\WpSms;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;

defined('ABSPATH') || exit;

class WpSmsGateway implements GatewayInterface
{
    public function getId(): string
    {
        return 'wp_sms';
    }

    public function getName(): string
    {
        return __('WP SMS (Legacy)', 'wp-sms');
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function send(MessageInterface $message): DeliveryResult
    {
        $result = apply_filters('wsms_send_sms', null, $message->getRecipient(), $message->getBody());

        if ($result === false || is_wp_error($result)) {
            $error = is_wp_error($result) ? $result->get_error_message() : __('SMS send failed', 'wp-sms');
            return DeliveryResult::failed($error);
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
