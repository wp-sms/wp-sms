<?php

namespace WSms\Flow\Action;

use WSms\Flow\Contracts\ActionInterface;
use WSms\Flow\Contracts\ActionResult;

defined('ABSPATH') || exit;

class UpdateUserMetaAction implements ActionInterface
{
    public function getId(): string
    {
        return 'update_user_meta';
    }

    public function getName(): string
    {
        return __('Update User Meta', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WordPress';
    }

    public function getConfigSchema(): array
    {
        return [
            'user_id'  => ['type' => 'string', 'label' => __('User ID', 'wp-sms'), 'template' => true, 'required' => true],
            'meta_key' => ['type' => 'string', 'label' => __('Meta Key', 'wp-sms'), 'required' => true],
            'value'    => ['type' => 'string', 'label' => __('Value', 'wp-sms'), 'template' => true, 'required' => true],
        ];
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
