<?php

namespace WSms\Integration\Telegram;

use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\Telegram\Actions\EditTelegramMessageAction;
use WSms\Integration\Telegram\Actions\SendTelegramDocumentAction;
use WSms\Integration\Telegram\Actions\SendTelegramMessageAction;
use WSms\Integration\Telegram\Actions\SendTelegramPhotoAction;
use WSms\Integration\Telegram\Triggers\CallbackQueryTrigger;
use WSms\Integration\Telegram\Triggers\ChannelPostTrigger;
use WSms\Integration\Telegram\Triggers\CommandReceivedTrigger;
use WSms\Integration\Telegram\Triggers\MemberJoinedTrigger;
use WSms\Integration\Telegram\Triggers\MessageReceivedTrigger;
use WSms\Telegram\TelegramBotClient;

defined('ABSPATH') || exit;

class TelegramIntegration implements IntegrationInterface
{
    public function __construct(
        private readonly TelegramBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'telegram';
    }

    public function getName(): string
    {
        return 'Telegram';
    }

    public function getDescription(): string
    {
        return __('Receive messages and commands, send messages, photos, and documents via Telegram bot.', 'wp-sms');
    }

    public function getCategory(): string
    {
        return 'communication';
    }

    public function getIcon(): string
    {
        return 'send';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getAuthType(): string
    {
        return 'api_key';
    }

    public function getAuthSchema(): array
    {
        return [
            'bot_token' => [
                'type' => 'string',
                'title' => __('Bot Token', 'wp-sms'),
                'description' => __('Create a bot via @BotFather on Telegram and paste the token here.', 'wp-sms'),
                'required' => true,
            ],
        ];
    }

    public function connect(array $credentials): array
    {
        $botToken = $credentials['bot_token'] ?? '';

        if (empty($botToken)) {
            throw new \RuntimeException(__('Bot token is required.', 'wp-sms'));
        }

        $client = new TelegramBotClient($botToken);
        $me = $client->getMe();

        if (!$me) {
            throw new \RuntimeException(__('Invalid bot token.', 'wp-sms'));
        }

        $webhookSecret = bin2hex(random_bytes(32));

        if (!$client->setWebhook(rest_url('wsms/v1/telegram/webhook'), $webhookSecret)) {
            throw new \RuntimeException(__('Webhook setup failed.', 'wp-sms'));
        }

        return [
            'bot_token'      => $botToken,
            'bot_username'   => $me['username'] ?? '',
            'webhook_secret' => $webhookSecret,
        ];
    }

    public function disconnect(): void
    {
        $this->botClient->deleteWebhook();
    }

    public function isConnected(): bool
    {
        $configs = get_option('wsms_integration_configs', []);

        return !empty($configs['telegram']['credentials']['bot_token']);
    }

    public function getTriggers(): array
    {
        return [
            new MessageReceivedTrigger(),
            new CommandReceivedTrigger(),
            new CallbackQueryTrigger($this->botClient),
            new MemberJoinedTrigger(),
            new ChannelPostTrigger(),
        ];
    }

    public function getActions(): array
    {
        return [
            new SendTelegramMessageAction($this->botClient),
            new SendTelegramPhotoAction($this->botClient),
            new SendTelegramDocumentAction($this->botClient),
            new EditTelegramMessageAction($this->botClient),
        ];
    }

    public function getCapabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
    }
}
