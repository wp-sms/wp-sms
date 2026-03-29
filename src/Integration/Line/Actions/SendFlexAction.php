<?php

namespace WSms\Integration\Line\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Line\LineBotClient;

defined('ABSPATH') || exit;

class SendFlexAction extends AbstractAction
{
    public function __construct(
        private readonly LineBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'line.send_flex';
    }

    public function getName(): string
    {
        return __('Send LINE Flex Message', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Send a rich Flex Message via LINE (bubble max 10KB, carousel max 50KB)', 'wp-sms');
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
                'description' => __('LINE user ID to send the Flex Message to', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => '{{userId}}',
            ],
            'flex_json' => [
                'type'        => 'text',
                'label'       => __('Flex JSON', 'wp-sms'),
                'description' => __('Flex Message container JSON (bubble or carousel)', 'wp-sms'),
                'template'    => true,
                'required'    => true,
            ],
            'alt_text' => [
                'type'        => 'string',
                'label'       => __('Alt Text', 'wp-sms'),
                'description' => __('Alternative text shown on old clients and notifications (max 400 chars)', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => 'Order Confirmation',
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
        $flexJson = $config['flex_json'] ?? '';
        $altText = $config['alt_text'] ?? '';

        if ($recipient === '' || $flexJson === '' || $altText === '') {
            return ActionResult::failure('Recipient, Flex JSON, and alt text are required.');
        }

        $contents = json_decode($flexJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ActionResult::failure('Invalid Flex JSON: ' . json_last_error_msg());
        }

        // Validate size limits.
        $jsonSize = strlen($flexJson);
        $containerType = $contents['type'] ?? '';

        if ($containerType === 'bubble' && $jsonSize > 10240) {
            return ActionResult::failure('Flex bubble JSON exceeds 10KB limit.');
        }

        if ($containerType === 'carousel' && $jsonSize > 51200) {
            return ActionResult::failure('Flex carousel JSON exceeds 50KB limit.');
        }

        $messages = [[
            'type'     => 'flex',
            'altText'  => mb_substr($altText, 0, 400),
            'contents' => $contents,
        ]];

        $retryKey = wp_generate_uuid4();
        $result = $this->botClient->pushMessage($recipient, $messages, $retryKey);

        if ($result === null) {
            return ActionResult::failure('Failed to send LINE Flex Message.');
        }

        return ActionResult::success();
    }
}
