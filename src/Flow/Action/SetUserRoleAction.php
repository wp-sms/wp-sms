<?php

namespace WSms\Flow\Action;

use WSms\Flow\Contracts\ActionInterface;
use WSms\Flow\Contracts\ActionResult;

defined('ABSPATH') || exit;

class SetUserRoleAction implements ActionInterface
{
    public function getId(): string
    {
        return 'set_user_role';
    }

    public function getName(): string
    {
        return __('Set User Role', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WordPress';
    }

    public function getConfigSchema(): array
    {
        return [
            'user_id' => ['type' => 'string', 'label' => __('User ID', 'wp-sms'), 'template' => true, 'required' => true],
            'role'    => ['type' => 'string', 'label' => __('Role', 'wp-sms'), 'required' => true],
        ];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $userId = (int) ($config['user_id'] ?? 0);
        $role = $config['role'] ?? '';

        if (!$userId || !$role) {
            return ActionResult::failure(__('user_id and role are required', 'wp-sms'));
        }

        $user = get_userdata($userId);
        if (!$user) {
            return ActionResult::failure(__('User not found', 'wp-sms'));
        }

        wp_update_user(['ID' => $userId, 'role' => $role]);

        return ActionResult::success(['user_id' => $userId, 'role' => $role]);
    }
}
