<?php

namespace WSms\Integration\WooCommerce;

use WSms\Flow\Contracts\TriggerInterface;
use WSms\Integration\Contracts\IntegrationInterface;

defined('ABSPATH') || exit;

class WooCommerceIntegration implements IntegrationInterface
{
    public function getId(): string
    {
        return 'woocommerce';
    }

    public function getName(): string
    {
        return 'WooCommerce';
    }

    public function getCategory(): string
    {
        return 'ecommerce';
    }

    public function getIcon(): string
    {
        return 'dashicons-cart';
    }

    public function isAvailable(): bool
    {
        return class_exists('WooCommerce');
    }

    public function getAuthType(): string
    {
        return 'none';
    }

    public function getAuthSchema(): array
    {
        return [];
    }

    public function getTriggers(): array
    {
        return [
            new class implements TriggerInterface {
                public function getId(): string { return 'woocommerce.order_created'; }
                public function getName(): string { return __('Order Created', 'wp-sms'); }
                public function getGroup(): string { return 'WooCommerce'; }
                public function getPayloadSchema(): array {
                    return [
                        'order_id' => ['type' => 'integer', 'label' => __('Order ID', 'wp-sms')],
                        'order'    => ['type' => 'object', 'label' => __('Order Data', 'wp-sms')],
                        'customer' => ['type' => 'object', 'label' => __('Customer Data', 'wp-sms')],
                    ];
                }
                public function subscribe(callable $callback): void {
                    add_action('woocommerce_new_order', function (int $orderId) use ($callback) {
                        $order = wc_get_order($orderId);
                        if (!$order) return;
                        $callback([
                            'order_id' => $orderId,
                            'order'    => [
                                'id'     => $orderId,
                                'total'  => $order->get_total(),
                                'status' => $order->get_status(),
                            ],
                            'customer' => [
                                'email' => $order->get_billing_email(),
                                'phone' => $order->get_billing_phone(),
                                'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                            ],
                        ]);
                    });
                }
            },
            new class implements TriggerInterface {
                public function getId(): string { return 'woocommerce.order_completed'; }
                public function getName(): string { return __('Order Completed', 'wp-sms'); }
                public function getGroup(): string { return 'WooCommerce'; }
                public function getPayloadSchema(): array {
                    return [
                        'order_id' => ['type' => 'integer', 'label' => __('Order ID', 'wp-sms')],
                        'order'    => ['type' => 'object', 'label' => __('Order Data', 'wp-sms')],
                        'customer' => ['type' => 'object', 'label' => __('Customer Data', 'wp-sms')],
                    ];
                }
                public function subscribe(callable $callback): void {
                    add_action('woocommerce_order_status_completed', function (int $orderId) use ($callback) {
                        $order = wc_get_order($orderId);
                        if (!$order) return;
                        $callback([
                            'order_id' => $orderId,
                            'order'    => [
                                'id'     => $orderId,
                                'total'  => $order->get_total(),
                                'status' => 'completed',
                            ],
                            'customer' => [
                                'email' => $order->get_billing_email(),
                                'phone' => $order->get_billing_phone(),
                                'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                            ],
                        ]);
                    });
                }
            },
            new class implements TriggerInterface {
                public function getId(): string { return 'woocommerce.payment_failed'; }
                public function getName(): string { return __('Payment Failed', 'wp-sms'); }
                public function getGroup(): string { return 'WooCommerce'; }
                public function getPayloadSchema(): array {
                    return [
                        'order_id' => ['type' => 'integer', 'label' => __('Order ID', 'wp-sms')],
                    ];
                }
                public function subscribe(callable $callback): void {
                    add_action('woocommerce_order_status_failed', function (int $orderId) use ($callback) {
                        $callback(['order_id' => $orderId]);
                    });
                }
            },
        ];
    }

    public function getActions(): array
    {
        return [];
    }

    public function boot(): void
    {
    }
}
