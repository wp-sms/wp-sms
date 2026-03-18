<?php

namespace WSms\Integration\WooCommerce\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

defined('ABSPATH') || exit;

class OrderCompletedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'woocommerce.order_completed';
    }

    public function getName(): string
    {
        return __('Order Completed', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when an order is completed', 'wp-sms');
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
            'order' => [
                'type' => 'object',
                'label' => __('Order Data', 'wp-sms'),
                'description' => __('Order details including total', 'wp-sms'),
                'properties' => PayloadSchemas::wooOrder(),
                'example' => ['id' => 1001, 'total' => '59.99', 'status' => 'completed'],
            ],
            'customer' => [
                'type' => 'object',
                'label' => __('Customer Data', 'wp-sms'),
                'description' => __('Customer contact information', 'wp-sms'),
                'properties' => PayloadSchemas::wooCustomer(),
                'example' => ['email' => 'customer@example.com', 'phone' => '+1234567890', 'name' => 'John Doe'],
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('woocommerce_order_status_completed', function (int $orderId) use ($callback) {
            $order = wc_get_order($orderId);
            if (!$order) {
                return;
            }
            $callback([
                'order_id' => $orderId,
                'order' => [
                    'id' => $orderId,
                    'total' => $order->get_total(),
                    'status' => 'completed',
                ],
                'customer' => [
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                    'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                ],
            ]);
        });
    }
}
