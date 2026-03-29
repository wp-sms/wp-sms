<?php

namespace WSms\Integration\Telegram\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Telegram\TelegramBotClient;

defined('ABSPATH') || exit;

class SendTelegramPhotoAction extends AbstractAction
{
    public function __construct(
        private readonly TelegramBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'telegram.send_photo';
    }

    public function getName(): string
    {
        return __('Send Telegram Photo', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Send a photo via Telegram bot', 'wp-sms');
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
                'description' => __('The Telegram chat ID to send the photo to', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => '{{chat_id}}',
            ],
            'photo' => [
                'type' => 'string',
                'label' => __('Photo URL', 'wp-sms'),
                'description' => __('URL of the photo to send', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => 'https://example.com/photo.jpg',
            ],
            'caption' => [
                'type' => 'text',
                'label' => __('Caption', 'wp-sms'),
                'description' => __('Photo caption', 'wp-sms'),
                'template' => true,
                'example' => 'Check out this photo!',
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
        $photo = $config['photo'] ?? '';

        if ($chatId === 0 || $photo === '') {
            return ActionResult::failure('Chat ID and photo URL are required.');
        }

        $options = [];

        if (!empty($config['caption'])) {
            $options['caption'] = $config['caption'];
        }
        if (!empty($config['parse_mode'])) {
            $options['parse_mode'] = $config['parse_mode'];
        }

        $result = $this->botClient->sendPhoto($chatId, $photo, $options);

        if ($result === null) {
            return ActionResult::failure('Failed to send Telegram photo.');
        }

        return ActionResult::success([
            'message_id' => $result['message_id'] ?? null,
        ]);
    }
}
