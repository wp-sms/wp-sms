<?php

namespace WSms\Integration\Telegram\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;
use WSms\Telegram\TelegramBotClient;

defined('ABSPATH') || exit;

class CallbackQueryTrigger extends AbstractTrigger
{
    public function __construct(
        private readonly TelegramBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'telegram.callback_query';
    }

    public function getName(): string
    {
        return __('Button Clicked', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a user clicks an inline keyboard button', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'Telegram';
    }

    public function getPayloadSchema(): array
    {
        return [
            'callback_id' => [
                'type' => 'string',
                'label' => __('Callback ID', 'wp-sms'),
                'example' => '4382bfdwdsb323b2d9',
            ],
            'chat_id' => [
                'type' => 'integer',
                'label' => __('Chat ID', 'wp-sms'),
                'example' => 123456789,
            ],
            'message_id' => [
                'type' => 'integer',
                'label' => __('Message ID', 'wp-sms'),
                'example' => 42,
            ],
            'data' => [
                'type' => 'string',
                'label' => __('Callback Data', 'wp-sms'),
                'description' => __('The data string attached to the button', 'wp-sms'),
                'example' => 'action:confirm',
            ],
            'from' => [
                'type' => 'object',
                'label' => __('From', 'wp-sms'),
                'properties' => PayloadSchemas::telegramUser(),
                'example' => ['id' => 123456789, 'first_name' => 'John', 'last_name' => 'Doe', 'username' => 'johndoe'],
            ],
        ];
    }

    public function getFilterSchema(): array
    {
        return [
            'data' => [
                'type' => 'string',
                'label' => __('Callback Data', 'wp-sms'),
                'description' => __('Only trigger for this callback data value', 'wp-sms'),
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_telegram_callback_query', function (array $query) use ($callback) {
            $callback([
                'callback_id' => $query['id'] ?? '',
                'chat_id'     => (int) ($query['message']['chat']['id'] ?? 0),
                'message_id'  => (int) ($query['message']['message_id'] ?? 0),
                'data'        => $query['data'] ?? '',
                'from'        => PayloadSchemas::extractTelegramUser($query['from'] ?? []),
            ]);

            // Auto-answer to stop the button loading spinner
            $callbackId = $query['id'] ?? '';
            if ($callbackId !== '') {
                $this->botClient->answerCallbackQuery($callbackId);
            }
        });
    }
}
