<?php

namespace WSms\Integration\Telegram\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Telegram\TelegramBotClient;

defined('ABSPATH') || exit;

class EditTelegramMessageAction extends AbstractAction
{
    public function __construct(
        private readonly TelegramBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'telegram.edit_message';
    }

    public function getName(): string
    {
        return __('Edit Telegram Message', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Edit an existing Telegram message', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('Telegram', 'wp-sms');
    }

    public function getOutputSchema(): array
    {
        return [
            'message_id' => ['type' => 'integer', 'title' => 'Message ID'],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'chat_id' => [
                'type' => 'string',
                'label' => __('Chat ID', 'wp-sms'),
                'description' => __('The chat containing the message to edit', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => '{{chat_id}}',
            ],
            'message_id' => [
                'type' => 'string',
                'label' => __('Message ID', 'wp-sms'),
                'description' => __('The ID of the message to edit', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => '{{message_id}}',
            ],
            'text' => [
                'type' => 'text',
                'label' => __('New Text', 'wp-sms'),
                'description' => __('The new message text', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => 'Updated: your order has been shipped!',
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
                'example' => '{"inline_keyboard":[[{"text":"Done","callback_data":"done"}]]}',
            ],
        ];
    }

    public function getPlaceholders(string $triggerType): array
    {
        if ($triggerType === 'telegram.callback_query') {
            return [
                'chat_id'    => '{{chat_id}}',
                'message_id' => '{{message_id}}',
            ];
        }

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
        $messageId = (int) ($config['message_id'] ?? 0);
        $text = $config['text'] ?? '';

        if ($chatId === 0 || $messageId === 0 || $text === '') {
            return ActionResult::failure('Chat ID, message ID, and text are required.');
        }

        $options = [
            'parse_mode' => $config['parse_mode'] ?? 'HTML',
        ];

        if (!empty($config['reply_markup'])) {
            $decoded = json_decode($config['reply_markup'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ActionResult::failure('Invalid reply markup JSON.');
            }
            $options['reply_markup'] = $decoded;
        }

        $result = $this->botClient->editMessageText($chatId, $messageId, $text, $options);

        if ($result === null) {
            return ActionResult::failure('Failed to edit Telegram message.');
        }

        return ActionResult::success([
            'message_id' => $result['message_id'] ?? null,
        ]);
    }
}
