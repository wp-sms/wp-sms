<?php

namespace WSms\Integration\WooCommerce\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

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

    public function getDescription(): string
    {
        return __('Fires when a product is purchased', 'wp-sms');
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
            'item_total' => [
                'type' => 'string',
                'label' => __('Item Total', 'wp-sms'),
                'description' => __('Total price for this line item', 'wp-sms'),
                'example' => '29.99',
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

    public function getFilterSchema(): array
    {
        return [
            'product_id' => [
                'type'        => 'integer',
                'label'       => __('Product', 'wp-sms'),
                'description' => __('Only trigger for this product', 'wp-sms'),
                'dynamic'     => true,
            ],
        ];
    }

    public function getFilterOptions(string $fieldKey): array
    {
        if ($fieldKey === 'product_id') {
            $products = wc_get_products(['limit' => 100, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);
            return array_map(fn ($product) => [
                'value' => (string) $product->get_id(),
                'label' => $product->get_name(),
            ], $products);
        }

        return [];
    }

    public function subscribe(callable $callback): void
    {
        add_action('woocommerce_order_status_completed', function (int $orderId) use ($callback) {
            $order = wc_get_order($orderId);
            if (!$order) {
                return;
            }

            $customer = PayloadSchemas::extractWooCustomer($order);

            foreach ($order->get_items() as $item) {
                $callback([
                    'order_id'     => $orderId,
                    'product_id'   => $item->get_product_id(),
                    'product_name' => $item->get_name(),
                    'quantity'     => $item->get_quantity(),
                    'item_total'   => $item->get_total(),
                    'customer'     => $customer,
                ]);
            }
        });
    }
}
