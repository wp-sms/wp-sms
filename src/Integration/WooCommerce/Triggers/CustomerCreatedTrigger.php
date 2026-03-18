<?php

namespace WSms\Integration\WooCommerce\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class CustomerCreatedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'woocommerce.customer_created';
    }

    public function getName(): string
    {
        return __('Customer Created', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a new customer is created', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WooCommerce';
    }

    public function getPayloadSchema(): array
    {
        return [
            'customer_id' => [
                'type' => 'integer',
                'label' => __('Customer ID', 'wp-sms'),
                'description' => __('The WordPress user ID of the new customer', 'wp-sms'),
                'example' => 42,
            ],
            'email' => [
                'type' => 'string',
                'label' => __('Email', 'wp-sms'),
                'format' => 'email',
                'description' => __('Customer email address', 'wp-sms'),
                'example' => 'customer@example.com',
            ],
            'first_name' => [
                'type' => 'string',
                'label' => __('First Name', 'wp-sms'),
                'description' => __('Customer first name', 'wp-sms'),
                'example' => 'John',
            ],
            'last_name' => [
                'type' => 'string',
                'label' => __('Last Name', 'wp-sms'),
                'description' => __('Customer last name', 'wp-sms'),
                'example' => 'Doe',
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('woocommerce_created_customer', function (int $customerId, array $newCustomerData) use ($callback) {
            $callback([
                'customer_id' => $customerId,
                'email' => $newCustomerData['user_email'] ?? '',
                'first_name' => $newCustomerData['first_name'] ?? '',
                'last_name' => $newCustomerData['last_name'] ?? '',
            ]);
        }, 10, 2);
    }
}
