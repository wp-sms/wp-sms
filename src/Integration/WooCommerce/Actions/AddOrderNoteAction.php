<?php

namespace WSms\Integration\WooCommerce\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;

defined('ABSPATH') || exit;

class AddOrderNoteAction extends AbstractAction
{
    public function getId(): string
    {
        return 'add_order_note';
    }

    public function getName(): string
    {
        return __('Add Order Note', 'wp-sms');
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
                'description' => __('The order to add the note to. Usually {{order_id}} from the trigger.', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => '{{order_id}}',
            ],
            'note' => [
                'type' => 'text',
                'label' => __('Note', 'wp-sms'),
                'description' => __('Write the note text. Use {{variables}} to include trigger data.', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => 'Customer contacted via SMS.',
            ],
            'is_customer_note' => [
                'type' => 'boolean',
                'label' => __('Customer Note', 'wp-sms'),
                'description' => __('Whether the note is visible to the customer', 'wp-sms'),
                'default' => false,
                'example' => false,
            ],
        ];
    }

    public function getPlaceholders(string $triggerType): array
    {
        return match ($triggerType) {
            'woocommerce.order_created' => [
                'note' => 'Order #{{order_id}} from {{customer.name}} — Total: ${{order.total}}',
            ],
            'woocommerce.order_status_changed' => [
                'note' => 'Status changed from {{old_status}} to {{new_status}} for order #{{order_id}}.',
            ],
            default => [],
        };
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $orderId = (int) ($config['order_id'] ?? 0);
        $note = $config['note'] ?? '';

        if (!$orderId || !$note) {
            return ActionResult::failure(__('order_id and note are required', 'wp-sms'));
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            return ActionResult::failure(__('Order not found', 'wp-sms'));
        }

        $isCustomerNote = (bool) ($config['is_customer_note'] ?? false);
        $noteId = $order->add_order_note($note, $isCustomerNote);

        return ActionResult::success(['order_id' => $orderId, 'note_id' => $noteId]);
    }
}
