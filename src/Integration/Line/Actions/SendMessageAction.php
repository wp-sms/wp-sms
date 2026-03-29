<?php

namespace WSms\Integration\Line\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Line\LineBotClient;

defined('ABSPATH') || exit;

class SendMessageAction extends AbstractAction
{
    public function __construct(
        private readonly LineBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'line.send_message';
    }

    public function getName(): string
    {
        return __('Send LINE Message', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Send a text message to a LINE user (max 5,000 characters)', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('LINE', 'wp-sms');
    }

    public function getOutputSchema(): array
    {
        return [];
    }

    public function getConfigSchema(): array
    {
        return [
            'recipient' => [
                'type'        => 'string',
                'label'       => __('Recipient User ID', 'wp-sms'),
                'description' => __('LINE user ID to send the message to', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => '{{userId}}',
            ],
            'text' => [
                'type'        => 'text',
                'label'       => __('Message Text', 'wp-sms'),
                'description' => __('The message content (max 5,000 characters)', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => 'Hello! Your order has been confirmed.',
            ],
            'quick_reply' => [
                'type'        => 'text',
                'label'       => __('Quick Reply JSON', 'wp-sms'),
                'description' => __('JSON for quick reply buttons (max 13 items, mobile only)', 'wp-sms'),
                'template'    => true,
                'example'     => '{"items":[{"type":"action","action":{"type":"message","label":"Yes","text":"Yes"}}]}',
            ],
        ];
    }

    public function getPlaceholders(string $triggerType): array
    {
        if (str_starts_with($triggerType, 'line.')) {
            return ['recipient' => '{{userId}}'];
        }

        return [];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $recipient = $config['recipient'] ?? '';
        $text = $config['text'] ?? '';

        if ($recipient === '' || $text === '') {
            return ActionResult::failure('Recipient and text are required.');
        }

        // Line text messages limited to 5,000 chars.
        $text = mb_substr($text, 0, 5000);

        $messages = [['type' => 'text', 'text' => $text]];

        if (!empty($config['quick_reply'])) {
            $quickReply = json_decode($config['quick_reply'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $messages[0]['quickReply'] = $quickReply;
            }
        }

        $retryKey = wp_generate_uuid4();
        $result = $this->botClient->pushMessage($recipient, $messages, $retryKey);

        if ($result === null) {
            return ActionResult::failure('Failed to send LINE message.');
        }

        return ActionResult::success();
    }
}
