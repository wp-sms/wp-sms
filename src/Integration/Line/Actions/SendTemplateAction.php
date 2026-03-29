<?php

namespace WSms\Integration\Line\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Line\LineBotClient;

defined('ABSPATH') || exit;

class SendTemplateAction extends AbstractAction
{
    public function __construct(
        private readonly LineBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'line.send_template';
    }

    public function getName(): string
    {
        return __('Send LINE Template', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Send a template message (buttons, confirm, carousel, or image carousel)', 'wp-sms');
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
                'description' => __('LINE user ID to send the template to', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => '{{userId}}',
            ],
            'template_json' => [
                'type'        => 'text',
                'label'       => __('Template JSON', 'wp-sms'),
                'description' => __('Template object JSON (buttons, confirm, carousel, or image_carousel type)', 'wp-sms'),
                'template'    => true,
                'required'    => true,
            ],
            'alt_text' => [
                'type'        => 'string',
                'label'       => __('Alt Text', 'wp-sms'),
                'description' => __('Alternative text shown on old clients and notifications (max 400 chars)', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => 'Please choose an option',
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
        $templateJson = $config['template_json'] ?? '';
        $altText = $config['alt_text'] ?? '';

        if ($recipient === '' || $templateJson === '' || $altText === '') {
            return ActionResult::failure('Recipient, template JSON, and alt text are required.');
        }

        $template = json_decode($templateJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ActionResult::failure('Invalid template JSON: ' . json_last_error_msg());
        }

        $messages = [[
            'type'     => 'template',
            'altText'  => mb_substr($altText, 0, 400),
            'template' => $template,
        ]];

        $retryKey = wp_generate_uuid4();
        $result = $this->botClient->pushMessage($recipient, $messages, $retryKey);

        if ($result === null) {
            return ActionResult::failure('Failed to send LINE template message.');
        }

        return ActionResult::success();
    }
}
