<?php

namespace WSms\Integration\Telegram\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

defined('ABSPATH') || exit;

class MessageReceivedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'telegram.message_received';
    }

    public function getName(): string
    {
        return __('Message Received', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a non-command message is received by the bot', 'wp-sms');
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
            'text' => [
                'type' => 'string',
                'label' => __('Text', 'wp-sms'),
                'example' => 'Hello there!',
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
            'has_media' => [
                'type' => 'boolean',
                'label' => __('Has Media', 'wp-sms'),
                'example' => false,
            ],
            'media_type' => [
                'type' => 'string',
                'label' => __('Media Type', 'wp-sms'),
                'example' => '',
            ],
        ];
    }

    public function getFilterSchema(): array
    {
        return [
            'chat.type' => [
                'type' => 'string',
                'label' => __('Chat Type', 'wp-sms'),
                'description' => __('Only trigger for this chat type', 'wp-sms'),
                'enum' => ['private', 'group', 'supergroup'],
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_telegram_message', function (array $message) use ($callback) {
            // Skip commands (handled by CommandReceivedTrigger)
            $text = $message['text'] ?? '';
            if (is_string($text) && str_starts_with(trim($text), '/')) {
                return;
            }

            // Skip new_chat_members messages (handled by MemberJoinedTrigger)
            if (!empty($message['new_chat_members'])) {
                return;
            }

            $mediaType = PayloadSchemas::detectTelegramMediaType($message);
            $displayText = $message['text'] ?? $message['caption'] ?? '';

            $callback([
                'chat_id'    => (int) ($message['chat']['id'] ?? 0),
                'message_id' => (int) ($message['message_id'] ?? 0),
                'text'       => $displayText,
                'from'       => PayloadSchemas::extractTelegramUser($message['from'] ?? []),
                'chat'       => PayloadSchemas::extractTelegramChat($message['chat'] ?? []),
                'date'       => (int) ($message['date'] ?? 0),
                'has_media'  => $mediaType !== '',
                'media_type' => $mediaType,
            ]);
        });
    }

}
