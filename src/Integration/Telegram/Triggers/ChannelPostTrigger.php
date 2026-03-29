<?php

namespace WSms\Integration\Telegram\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

defined('ABSPATH') || exit;

class ChannelPostTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'telegram.channel_post';
    }

    public function getName(): string
    {
        return __('Channel Post', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a new post is published in a Telegram channel', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('Telegram', 'wp-sms');
    }

    public function getPayloadSchema(): array
    {
        return [
            'chat_id' => [
                'type' => 'integer',
                'label' => __('Chat ID', 'wp-sms'),
                'example' => -1001234567890,
            ],
            'message_id' => [
                'type' => 'integer',
                'label' => __('Message ID', 'wp-sms'),
                'example' => 42,
            ],
            'text' => [
                'type' => 'string',
                'label' => __('Text', 'wp-sms'),
                'example' => 'Channel announcement',
            ],
            'chat' => [
                'type' => 'object',
                'label' => __('Chat', 'wp-sms'),
                'properties' => PayloadSchemas::telegramChat(),
                'example' => ['id' => -1001234567890, 'type' => 'channel', 'title' => 'My Channel'],
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
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_telegram_channel_post', function (array $post) use ($callback) {
            $callback([
                'chat_id'    => (int) ($post['chat']['id'] ?? 0),
                'message_id' => (int) ($post['message_id'] ?? 0),
                'text'       => $post['text'] ?? $post['caption'] ?? '',
                'chat'       => PayloadSchemas::extractTelegramChat($post['chat'] ?? []),
                'date'       => (int) ($post['date'] ?? 0),
                'has_media'  => PayloadSchemas::detectTelegramMediaType($post) !== '',
            ]);
        });
    }
}
