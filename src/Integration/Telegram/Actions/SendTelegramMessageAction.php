<?php

namespace WSms\Integration\Telegram\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Telegram\TelegramBotClient;

defined('ABSPATH') || exit;

class SendTelegramMessageAction extends AbstractAction
{
    public function __construct(
        private readonly TelegramBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'telegram.send_message';
    }

    public function getName(): string
    {
        return __('Send Telegram Message', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Send a text message via Telegram bot', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'Telegram';
    }

    public function getConfigSchema(): array
    {
        return [
            'chat_id' => [
                'type' => 'string',
                'label' => __('Chat ID', 'wp-sms'),
                'description' => __('The Telegram chat ID to send the message to', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => '{{chat_id}}',
            ],
            'text' => [
                'type' => 'text',
                'label' => __('Message Text', 'wp-sms'),
                'description' => __('The message content to send', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => 'Hello! Your order has been confirmed.',
            ],
            'parse_mode' => [
                'type' => 'string',
                'label' => __('Parse Mode', 'wp-sms'),
                'description' => __('How to format the message text', 'wp-sms'),
                'enum' => ['HTML', 'MarkdownV2'],
                'default' => 'HTML',
                'example' => 'HTML',
            ],
            'reply_markup' => [
                'type' => 'text',
                'label' => __('Reply Markup', 'wp-sms'),
                'description' => __('JSON string for inline keyboard markup', 'wp-sms'),
                'template' => true,
                'example' => '{"inline_keyboard":[[{"text":"Confirm","callback_data":"confirm"}]]}',
            ],
            'reply_to_message_id' => [
                'type' => 'string',
                'label' => __('Reply To Message ID', 'wp-sms'),
                'description' => __('Message ID to reply to', 'wp-sms'),
                'template' => true,
                'example' => '{{message_id}}',
            ],
        ];
    }

    public function getPlaceholders(string $triggerType): array
    {
        if (str_starts_with($triggerType, 'telegram.')) {
            return [
                'chat_id' => '{{chat_id}}',
            ];
        }

        return [];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $chatId = (int) ($config['chat_id'] ?? 0);
        $text = $config['text'] ?? '';

        if ($chatId === 0 || $text === '') {
            return ActionResult::failure('Chat ID and text are required.');
        }

        $options = [
            'parse_mode' => $config['parse_mode'] ?? 'HTML',
        ];

        if (!empty($config['reply_to_message_id'])) {
            $options['reply_to_message_id'] = $config['reply_to_message_id'];
        }

        if (!empty($config['reply_markup'])) {
            $decoded = json_decode($config['reply_markup'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ActionResult::failure('Invalid reply markup JSON.');
            }
            $options['reply_markup'] = $decoded;
        }

        $result = $this->botClient->sendMessageAdvanced($chatId, $text, $options);

        if ($result === null) {
            return ActionResult::failure('Failed to send Telegram message.');
        }

        return ActionResult::success([
            'message_id' => $result['message_id'] ?? null,
        ]);
    }
}
