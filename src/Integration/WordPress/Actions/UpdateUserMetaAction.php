<?php

namespace WSms\Integration\WordPress\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;

defined('ABSPATH') || exit;

class UpdateUserMetaAction extends AbstractAction
{
    public function getId(): string
    {
        return 'update_user_meta';
    }

    public function getName(): string
    {
        return __('Update User Meta', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Set or update a user meta field', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('WordPress', 'wp-sms');
    }

    public function getOutputSchema(): array
    {
        return [
            'user_id'  => ['type' => 'integer', 'title' => 'User ID'],
            'meta_key' => ['type' => 'string', 'title' => 'Meta Key'],
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
            'meta_key' => [
                'type' => 'string',
                'label' => __('Meta Key', 'wp-sms'),
                'description' => __('The user meta key to set', 'wp-sms'),
                'required' => true,
                'example' => 'custom_field',
            ],
            'value' => [
                'type' => 'string',
                'label' => __('Value', 'wp-sms'),
                'description' => __('The value to store', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => 'some_value',
            ],
        ];
    }

    public function getPlaceholders(string $triggerType): array
    {
        return match ($triggerType) {
            'wordpress.user_register' => [
                'user_id' => '{{user_id}}',
            ],
            default => [],
        };
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $userId = (int) ($config['user_id'] ?? 0);
        $metaKey = $config['meta_key'] ?? '';
        $value = $config['value'] ?? '';

        if (!$userId || !$metaKey) {
            return ActionResult::failure(__('user_id and meta_key are required', 'wp-sms'));
        }

        update_user_meta($userId, $metaKey, $value);

        return ActionResult::success(['user_id' => $userId, 'meta_key' => $metaKey]);
    }
}
