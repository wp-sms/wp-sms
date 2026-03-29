<?php

namespace WSms\Integration\Line\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

defined('ABSPATH') || exit;

class MessageReceivedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'line.message_received';
    }

    public function getName(): string
    {
        return __('Message Received', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a text message is received by the LINE bot', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('LINE', 'wp-sms');
    }

    public function getPayloadSchema(): array
    {
        return [
            'userId' => [
                'type'    => 'string',
                'label'   => __('User ID', 'wp-sms'),
                'example' => 'U1234567890abcdef1234567890abcdef',
            ],
            'messageId' => [
                'type'    => 'string',
                'label'   => __('Message ID', 'wp-sms'),
                'example' => '468789577270562817',
            ],
            'messageType' => [
                'type'    => 'string',
                'label'   => __('Message Type', 'wp-sms'),
                'example' => 'text',
            ],
            'text' => [
                'type'    => 'string',
                'label'   => __('Text', 'wp-sms'),
                'example' => 'Hello!',
            ],
            'replyToken' => [
                'type'    => 'string',
                'label'   => __('Reply Token', 'wp-sms'),
                'example' => 'nHuyWiB7yP5Zw52FIkcQobQuGDXCTA',
                'description' => __('Token to reply for free (expires in 60 seconds, single use)', 'wp-sms'),
            ],
            'source' => [
                'type'       => 'object',
                'label'      => __('Source', 'wp-sms'),
                'properties' => PayloadSchemas::lineSource(),
                'example'    => ['type' => 'user', 'userId' => 'U1234...'],
            ],
            'timestamp' => [
                'type'    => 'integer',
                'label'   => __('Timestamp', 'wp-sms'),
                'example' => 1700000000000,
            ],
        ];
    }

    public function getFilterSchema(): array
    {
        return [
            'source.type' => [
                'type'        => 'string',
                'label'       => __('Source Type', 'wp-sms'),
                'description' => __('Only trigger for this source type', 'wp-sms'),
                'enum'        => ['user', 'group', 'room'],
            ],
            'messageType' => [
                'type'        => 'string',
                'label'       => __('Message Type', 'wp-sms'),
                'description' => __('Only trigger for this message type', 'wp-sms'),
                'enum'        => ['text', 'image', 'video', 'audio', 'file', 'location', 'sticker'],
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_line_message', function (array $event) use ($callback) {
            $message = $event['message'] ?? [];
            $source = $event['source'] ?? [];

            $callback([
                'userId'      => $source['userId'] ?? '',
                'messageId'   => $message['id'] ?? '',
                'messageType' => $message['type'] ?? '',
                'text'        => $message['text'] ?? '',
                'replyToken'  => $event['replyToken'] ?? '',
                'source'      => PayloadSchemas::extractLineSource($source),
                'timestamp'   => (int) ($event['timestamp'] ?? 0),
            ]);
        });
    }
}
