<?php

namespace WSms\Integration\WooCommerce\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class OrderStatusChangedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'woocommerce.order_status_changed';
    }

    public function getName(): string
    {
        return __('Order Status Changed', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WooCommerce';
    }

    public function getPayloadSchema(): array
    {
        return [
            'order_id' => [
                'type' => 'integer',
                'label' => __('Order ID', 'wp-sms'),
                'description' => __('The WooCommerce order ID', 'wp-sms'),
                'example' => 1001,
            ],
            'old_status' => [
                'type' => 'string',
                'label' => __('Old Status', 'wp-sms'),
                'description' => __('The previous order status', 'wp-sms'),
                'example' => 'pending',
            ],
            'new_status' => [
                'type' => 'string',
                'label' => __('New Status', 'wp-sms'),
                'description' => __('The new order status', 'wp-sms'),
                'example' => 'processing',
            ],
            'order' => [
                'type' => 'object',
                'label' => __('Order Data', 'wp-sms'),
                'description' => __('Order details', 'wp-sms'),
                'example' => ['id' => 1001, 'total' => '59.99'],
            ],
            'customer' => [
                'type' => 'object',
                'label' => __('Customer Data', 'wp-sms'),
                'description' => __('Customer contact information', 'wp-sms'),
                'example' => ['email' => 'customer@example.com', 'phone' => '+1234567890', 'name' => 'John Doe'],
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('woocommerce_order_status_changed', function (int $orderId, string $oldStatus, string $newStatus) use ($callback) {
            $order = wc_get_order($orderId);
            if (!$order) {
                return;
            }
            $callback([
                'order_id' => $orderId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'order' => [
                    'id' => $orderId,
                    'total' => $order->get_total(),
                ],
                'customer' => [
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                    'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                ],
            ]);
        }, 10, 3);
    }
}
