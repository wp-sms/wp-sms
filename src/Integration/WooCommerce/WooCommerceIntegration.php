<?php

namespace WSms\Integration\WooCommerce;

use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\WooCommerce\Actions\AddOrderNoteAction;
use WSms\Integration\WooCommerce\Actions\UpdateOrderStatusAction;
use WSms\Integration\WooCommerce\Triggers\CustomerCreatedTrigger;
use WSms\Integration\WooCommerce\Triggers\OrderCompletedTrigger;
use WSms\Integration\WooCommerce\Triggers\OrderCreatedTrigger;
use WSms\Integration\WooCommerce\Triggers\OrderStatusChangedTrigger;
use WSms\Integration\WooCommerce\Triggers\PaymentFailedTrigger;
use WSms\Integration\WooCommerce\Triggers\ProductPurchasedTrigger;

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
            new OrderCreatedTrigger(),
            new OrderCompletedTrigger(),
            new PaymentFailedTrigger(),
            new OrderStatusChangedTrigger(),
            new ProductPurchasedTrigger(),
            new CustomerCreatedTrigger(),
        ];
    }

    public function getActions(): array
    {
        return [
            new UpdateOrderStatusAction(),
            new AddOrderNoteAction(),
        ];
    }

    public function boot(): void
    {
    }
}
