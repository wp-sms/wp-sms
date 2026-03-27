<?php

namespace WSms\Integration\Line\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Line\LineBotClient;

defined('ABSPATH') || exit;

class ReplyAction extends AbstractAction
{
    public function __construct(
        private readonly LineBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'line.reply';
    }

    public function getName(): string
    {
        return __('Reply to LINE Event', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Reply to a webhook event for free (token expires in 60 seconds, single use)', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'LINE';
    }

    public function getOutputSchema(): array
    {
        return [];
    }

    public function getConfigSchema(): array
    {
        return [
            'reply_token' => [
                'type'        => 'string',
                'label'       => __('Reply Token', 'wp-sms'),
                'description' => __('Reply token from the trigger event (expires in 60s, single use)', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => '{{replyToken}}',
            ],
            'text' => [
                'type'        => 'text',
                'label'       => __('Message Text', 'wp-sms'),
                'description' => __('The reply message content (max 5,000 characters)', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => 'Thanks for your message!',
            ],
        ];
    }

    public function getPlaceholders(string $triggerType): array
    {
        if (str_starts_with($triggerType, 'line.')) {
            return ['reply_token' => '{{replyToken}}'];
        }

        return [];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $replyToken = $config['reply_token'] ?? '';
        $text = $config['text'] ?? '';

        if ($replyToken === '' || $text === '') {
            return ActionResult::failure('Reply token and text are required.');
        }

        $text = mb_substr($text, 0, 5000);

        $messages = [['type' => 'text', 'text' => $text]];
        $result = $this->botClient->replyMessage($replyToken, $messages);

        if ($result === null) {
            return ActionResult::failure('Failed to reply. The reply token may have expired (60s TTL) or already been used.');
        }

        return ActionResult::success();
    }
}
