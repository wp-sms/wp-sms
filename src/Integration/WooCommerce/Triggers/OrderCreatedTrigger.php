<?php

namespace WSms\Integration\WooCommerce\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

defined('ABSPATH') || exit;

class OrderCreatedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'woocommerce.order_created';
    }

    public function getName(): string
    {
        return __('Order Created', 'wp-sms');
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
                'description' => __('Order details including total and status', 'wp-sms'),
                'properties' => PayloadSchemas::wooOrder(),
                'example' => ['id' => 1001, 'total' => '59.99', 'status' => 'pending'],
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

    public function getSamplePayload(): ?array
    {
        if (!function_exists('wc_get_orders')) {
            return null;
        }

        $orders = wc_get_orders(['limit' => 1, 'orderby' => 'date', 'order' => 'DESC']);

        if (empty($orders)) {
            return null;
        }

        $order = $orders[0];

        return [
            'order_id' => $order->get_id(),
            'order' => [
                'id'     => $order->get_id(),
                'total'  => $order->get_total(),
                'status' => $order->get_status(),
            ],
            'customer' => [
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('woocommerce_new_order', function (int $orderId) use ($callback) {
            $order = wc_get_order($orderId);
            if (!$order) {
                return;
            }
            $callback([
                'order_id' => $orderId,
                'order' => [
                    'id' => $orderId,
                    'total' => $order->get_total(),
                    'status' => $order->get_status(),
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
