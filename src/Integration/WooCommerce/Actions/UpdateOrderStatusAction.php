<?php

namespace WSms\Integration\WooCommerce\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;

defined('ABSPATH') || exit;

class UpdateOrderStatusAction extends AbstractAction
{
    public function getId(): string
    {
        return 'update_order_status';
    }

    public function getName(): string
    {
        return __('Update Order Status', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WooCommerce';
    }

    public function getConfigSchema(): array
    {
        return [
            'order_id' => [
                'type' => 'string',
                'label' => __('Order ID', 'wp-sms'),
                'description' => __('The order to update. Usually {{order_id}} from the trigger.', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => '{{order_id}}',
            ],
            'status' => [
                'type' => 'string',
                'label' => __('Status', 'wp-sms'),
                'description' => __('The new order status', 'wp-sms'),
                'required' => true,
                'enum' => ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'],
                'example' => 'processing',
            ],
            'note' => [
                'type' => 'string',
                'label' => __('Note', 'wp-sms'),
                'description' => __('Optional note added to the order. Use {{variables}} for trigger data.', 'wp-sms'),
                'template' => true,
                'example' => 'Status updated by automation flow.',
            ],
        ];
    }

    public function getPlaceholders(string $triggerType): array
    {
        return match ($triggerType) {
            'woocommerce.order_created' => [
                'order_id' => '{{order_id}}',
                'note' => 'Auto-updated for order #{{order_id}}.',
            ],
            default => [],
        };
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $orderId = (int) ($config['order_id'] ?? 0);
        $status = $config['status'] ?? '';

        if (!$orderId || !$status) {
            return ActionResult::failure(__('order_id and status are required', 'wp-sms'));
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            return ActionResult::failure(__('Order not found', 'wp-sms'));
        }

        $note = $config['note'] ?? '';
        $order->update_status($status, $note);

        return ActionResult::success(['order_id' => $orderId, 'status' => $status]);
    }
}
