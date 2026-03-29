<?php

namespace WSms\Integration\Line\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Line\LineBotClient;

defined('ABSPATH') || exit;

class SendImageAction extends AbstractAction
{
    public function __construct(
        private readonly LineBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'line.send_image';
    }

    public function getName(): string
    {
        return __('Send LINE Image', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Send an image via LINE (JPEG/PNG, max 10MB, HTTPS required)', 'wp-sms');
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
                'description' => __('LINE user ID to send the image to', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => '{{userId}}',
            ],
            'image_url' => [
                'type'        => 'string',
                'label'       => __('Image URL', 'wp-sms'),
                'description' => __('HTTPS URL of the image (JPEG/PNG, max 10MB)', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => 'https://example.com/image.jpg',
            ],
            'preview_url' => [
                'type'        => 'string',
                'label'       => __('Preview URL', 'wp-sms'),
                'description' => __('HTTPS URL of the preview image (max 1MB). Uses image URL if empty.', 'wp-sms'),
                'template'    => true,
                'example'     => 'https://example.com/image-preview.jpg',
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
        $imageUrl = $config['image_url'] ?? '';

        if ($recipient === '' || $imageUrl === '') {
            return ActionResult::failure('Recipient and image URL are required.');
        }

        if (!str_starts_with($imageUrl, 'https://')) {
            return ActionResult::failure('Image URL must use HTTPS.');
        }

        $previewUrl = $config['preview_url'] ?? $imageUrl;

        if (!str_starts_with($previewUrl, 'https://')) {
            $previewUrl = $imageUrl;
        }

        $messages = [[
            'type'               => 'image',
            'originalContentUrl' => $imageUrl,
            'previewImageUrl'    => $previewUrl,
        ]];

        $retryKey = wp_generate_uuid4();
        $result = $this->botClient->pushMessage($recipient, $messages, $retryKey);

        if ($result === null) {
            return ActionResult::failure('Failed to send LINE image.');
        }

        return ActionResult::success();
    }
}
