<?php

namespace WSms\Integration\Line\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Line\LineBotClient;

defined('ABSPATH') || exit;

class SetRichMenuAction extends AbstractAction
{
    public function __construct(
        private readonly LineBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'line.set_rich_menu';
    }

    public function getName(): string
    {
        return __('Set Rich Menu for User', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Assign a rich menu to a specific LINE user', 'wp-sms');
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
            'userId' => [
                'type'        => 'string',
                'label'       => __('User ID', 'wp-sms'),
                'description' => __('LINE user ID to assign the rich menu to', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => '{{userId}}',
            ],
            'rich_menu_id' => [
                'type'        => 'string',
                'label'       => __('Rich Menu ID', 'wp-sms'),
                'description' => __('Rich menu ID from LINE Developers Console', 'wp-sms'),
                'required'    => true,
                'example'     => 'richmenu-1234567890abcdef',
            ],
        ];
    }

    public function getPlaceholders(string $triggerType): array
    {
        if (str_starts_with($triggerType, 'line.')) {
            return ['userId' => '{{userId}}'];
        }

        return [];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $userId = $config['userId'] ?? '';
        $richMenuId = $config['rich_menu_id'] ?? '';

        if ($userId === '' || $richMenuId === '') {
            return ActionResult::failure('User ID and rich menu ID are required.');
        }

        $success = $this->botClient->setRichMenuForUser($userId, $richMenuId);

        if (!$success) {
            return ActionResult::failure('Failed to set rich menu for user.');
        }

        return ActionResult::success();
    }
}
