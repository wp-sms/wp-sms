<?php

namespace WSms\Integration\WordPress\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;

defined('ABSPATH') || exit;

class DeleteUserAction extends AbstractAction
{
    public function getId(): string
    {
        return 'delete_user';
    }

    public function getName(): string
    {
        return __('Delete User', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WordPress';
    }

    public function getConfigSchema(): array
    {
        return [
            'user_id' => [
                'type' => 'string',
                'label' => __('User ID', 'wp-sms'),
                'description' => __('The WordPress user ID to delete', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => '{{user_id}}',
            ],
            'reassign_to' => [
                'type' => 'string',
                'label' => __('Reassign To', 'wp-sms'),
                'description' => __('User ID to reassign content to (optional)', 'wp-sms'),
                'template' => true,
                'example' => '1',
            ],
        ];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $userId = (int) ($config['user_id'] ?? 0);
        if (!$userId) {
            return ActionResult::failure(__('user_id is required', 'wp-sms'));
        }

        $user = get_userdata($userId);
        if (!$user) {
            return ActionResult::failure(__('User not found', 'wp-sms'));
        }

        $reassign = !empty($config['reassign_to']) ? (int) $config['reassign_to'] : null;
        wp_delete_user($userId, $reassign);

        return ActionResult::success(['user_id' => $userId, 'reassign_to' => $reassign]);
    }
}
