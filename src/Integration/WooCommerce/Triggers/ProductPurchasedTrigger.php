<?php

namespace WSms\Integration\WooCommerce\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class ProductPurchasedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'woocommerce.product_purchased';
    }

    public function getName(): string
    {
        return __('Product Purchased', 'wp-sms');
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
            'product_id' => [
                'type' => 'integer',
                'label' => __('Product ID', 'wp-sms'),
                'description' => __('The WooCommerce product ID', 'wp-sms'),
                'example' => 55,
            ],
            'product_name' => [
                'type' => 'string',
                'label' => __('Product Name', 'wp-sms'),
                'description' => __('The name of the purchased product', 'wp-sms'),
                'example' => 'Premium Widget',
            ],
            'quantity' => [
                'type' => 'integer',
                'label' => __('Quantity', 'wp-sms'),
                'description' => __('Number of items purchased', 'wp-sms'),
                'example' => 2,
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
        add_action('woocommerce_order_status_completed', function (int $orderId) use ($callback) {
            $order = wc_get_order($orderId);
            if (!$order) {
                return;
            }

            $customer = [
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            ];

            foreach ($order->get_items() as $item) {
                $callback([
                    'order_id' => $orderId,
                    'product_id' => $item->get_product_id(),
                    'product_name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'customer' => $customer,
                ]);
            }
        });
    }
}
