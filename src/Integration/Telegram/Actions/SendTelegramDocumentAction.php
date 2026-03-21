<?php

namespace WSms\Integration\Telegram\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Telegram\TelegramBotClient;

defined('ABSPATH') || exit;

class SendTelegramDocumentAction extends AbstractAction
{
    public function __construct(
        private readonly TelegramBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'telegram.send_document';
    }

    public function getName(): string
    {
        return __('Send Telegram Document', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Send a document via Telegram bot', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'Telegram';
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
                'description' => __('The Telegram chat ID to send the document to', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => '{{chat_id}}',
            ],
            'document' => [
                'type' => 'string',
                'label' => __('Document URL', 'wp-sms'),
                'description' => __('URL of the document to send', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => 'https://example.com/report.pdf',
            ],
            'caption' => [
                'type' => 'text',
                'label' => __('Caption', 'wp-sms'),
                'description' => __('Document caption', 'wp-sms'),
                'template' => true,
                'example' => 'Here is your report.',
            ],
            'parse_mode' => [
                'type' => 'string',
                'label' => __('Parse Mode', 'wp-sms'),
                'description' => __('How to format the caption', 'wp-sms'),
                'enum' => ['HTML', 'MarkdownV2'],
                'default' => 'HTML',
                'example' => 'HTML',
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
        $document = $config['document'] ?? '';

        if ($chatId === 0 || $document === '') {
            return ActionResult::failure('Chat ID and document URL are required.');
        }

        $options = [];

        if (!empty($config['caption'])) {
            $options['caption'] = $config['caption'];
        }
        if (!empty($config['parse_mode'])) {
            $options['parse_mode'] = $config['parse_mode'];
        }

        $result = $this->botClient->sendDocument($chatId, $document, $options);

        if ($result === null) {
            return ActionResult::failure('Failed to send Telegram document.');
        }

        return ActionResult::success([
            'message_id' => $result['message_id'] ?? null,
        ]);
    }
}
