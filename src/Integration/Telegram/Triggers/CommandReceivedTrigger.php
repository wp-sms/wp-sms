<?php

namespace WSms\Integration\Telegram\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

defined('ABSPATH') || exit;

class CommandReceivedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'telegram.command_received';
    }

    public function getName(): string
    {
        return __('Command Received', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a bot command (e.g. /order) is received', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'Telegram';
    }

    public function getPayloadSchema(): array
    {
        return [
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
            'command' => [
                'type' => 'string',
                'label' => __('Command', 'wp-sms'),
                'description' => __('The command without the leading slash', 'wp-sms'),
                'example' => 'start',
            ],
            'args' => [
                'type' => 'string',
                'label' => __('Arguments', 'wp-sms'),
                'description' => __('Everything after the command', 'wp-sms'),
                'example' => 'referral123',
            ],
            'from' => [
                'type' => 'object',
                'label' => __('From', 'wp-sms'),
                'properties' => PayloadSchemas::telegramUser(),
                'example' => ['id' => 123456789, 'first_name' => 'John', 'last_name' => 'Doe', 'username' => 'johndoe'],
            ],
            'chat' => [
                'type' => 'object',
                'label' => __('Chat', 'wp-sms'),
                'properties' => PayloadSchemas::telegramChat(),
                'example' => ['id' => 123456789, 'type' => 'private', 'title' => ''],
            ],
            'date' => [
                'type' => 'integer',
                'label' => __('Date', 'wp-sms'),
                'example' => 1700000000,
            ],
        ];
    }

    public function getFilterSchema(): array
    {
        return [
            'command' => [
                'type' => 'string',
                'label' => __('Command', 'wp-sms'),
                'description' => __('Only trigger for this command (without slash)', 'wp-sms'),
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_telegram_message', function (array $message) use ($callback) {
            $text = $message['text'] ?? '';

            if (!is_string($text)) {
                return;
            }

            // Match /command or /command@botname with optional arguments
            if (!preg_match('/^\/(\w+)(?:@\w+)?(?:\s+(.*))?$/s', trim($text), $matches)) {
                return;
            }

            $callback([
                'chat_id'    => (int) ($message['chat']['id'] ?? 0),
                'message_id' => (int) ($message['message_id'] ?? 0),
                'command'    => $matches[1],
                'args'       => trim($matches[2] ?? ''),
                'from'       => PayloadSchemas::extractTelegramUser($message['from'] ?? []),
                'chat'       => PayloadSchemas::extractTelegramChat($message['chat'] ?? []),
                'date'       => (int) ($message['date'] ?? 0),
            ]);
        });
    }
}
