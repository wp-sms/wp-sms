<?php

namespace WSms\Integration\WordPress\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Integration\WordPress\WordPressOptions;

defined('ABSPATH') || exit;

class SetUserRoleAction extends AbstractAction
{
    public function getId(): string
    {
        return 'set_user_role';
    }

    public function getName(): string
    {
        return __('Set User Role', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Change a user\'s WordPress role', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('WordPress', 'wp-sms');
    }

    public function getOutputSchema(): array
    {
        return [
            'user_id' => ['type' => 'integer', 'title' => 'User ID'],
            'role'    => ['type' => 'string', 'title' => 'Role'],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'user_id' => [
                'type' => 'string',
                'label' => __('User ID', 'wp-sms'),
                'description' => __('The WordPress user ID to update.', 'wp-sms'),
                'hint' => __('Usually {{user_id}} from the trigger.', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => '{{user_id}}',
            ],
            'role' => [
                'type' => 'string',
                'label' => __('Role', 'wp-sms'),
                'description' => __('The WordPress role to assign', 'wp-sms'),
                'required' => true,
                'dynamic' => true,
                'example' => 'editor',
            ],
        ];
    }

    public function getPlaceholders(string $triggerType): array
    {
        return match ($triggerType) {
            'wordpress.user_register',
            'wordpress.user_role_changed' => [
                'user_id' => '{{user_id}}',
            ],
            default => [],
        };
    }

    public function getConfigOptions(string $fieldKey, array $context = []): array
    {
        if ($fieldKey === 'role') {
            return WordPressOptions::roles();
        }

        return [];
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
