<?php

namespace WSms\Messaging\Gateway\Telegram;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Telegram\TelegramBotClient;

defined('ABSPATH') || exit;

class TelegramGateway implements GatewayInterface
{
    public function __construct(
        private readonly TelegramBotClient $telegramClient,
    ) {
    }

    public function getId(): string
    {
        return 'telegram';
    }

    public function getName(): string
    {
        return __('Telegram Bot', 'wp-sms');
    }

    public function getSupportedChannels(): array
    {
        return ['telegram'];
    }

    public function send(MessageInterface $message): DeliveryResult
    {
        $chatId = (int) $message->getRecipient();

        if ($chatId === 0) {
            return DeliveryResult::failed(__('Invalid Telegram chat ID', 'wp-sms'));
        }

        $sent = $this->telegramClient->sendMessage($chatId, $message->getBody());

        if (!$sent) {
            return DeliveryResult::failed(__('Telegram message send failed', 'wp-sms'));
        }

        return DeliveryResult::sent();
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'bot_token' => [
                    'type'     => 'string',
                    'label'    => __('Bot Token', 'wp-sms'),
                    'required' => true,
                ],
            ],
            'channels' => [],
        ];
    }

    public function validateConfig(array $config): bool
    {
        return !empty($config['shared']['bot_token'] ?? $config['bot_token'] ?? null);
    }

    public function isConfigured(): bool
    {
        $settings = get_option('wsms_auth_settings', []);
        return !empty($settings['telegram']['bot_token']);
    }

    public function isConfiguredForChannel(string $channel): bool
    {
        return $channel === 'telegram' && $this->isConfigured();
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Send messages via Telegram Bot API', 'wp-sms'),
            'website'     => 'https://core.telegram.org/bots',
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
}
