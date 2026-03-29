<?php

namespace WSms\MessagingButton;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class MessagingButtonTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'messaging_button.message_received';
    }

    public function getName(): string
    {
        return __('Widget Message Received', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a visitor submits a message through the messaging button widget', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('WSMS', 'wp-sms');
    }

    public function getPayloadSchema(): array
    {
        return [
            'contact_id' => [
                'type' => 'string',
                'label' => __('Contact ID', 'wp-sms'),
                'description' => __('The ULID of the created or matched contact', 'wp-sms'),
                'example' => '01HXYZ123ABC456DEF789GHI',
            ],
            'name' => [
                'type' => 'string',
                'label' => __('Name', 'wp-sms'),
                'description' => __('The name provided by the visitor', 'wp-sms'),
                'example' => 'John Doe',
            ],
            'email' => [
                'type' => 'string',
                'label' => __('Email', 'wp-sms'),
                'description' => __('The email address provided by the visitor', 'wp-sms'),
                'example' => 'john@example.com',
            ],
            'phone' => [
                'type' => 'string',
                'label' => __('Phone', 'wp-sms'),
                'description' => __('The phone number provided by the visitor', 'wp-sms'),
                'example' => '+1234567890',
            ],
            'message' => [
                'type' => 'string',
                'label' => __('Message', 'wp-sms'),
                'description' => __('The message content', 'wp-sms'),
                'example' => 'I have a question about your services.',
            ],
            'page_url' => [
                'type' => 'string',
                'label' => __('Page URL', 'wp-sms'),
                'description' => __('The page URL where the message was sent from', 'wp-sms'),
                'example' => 'https://example.com/pricing',
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_messaging_button_message', $callback, 20);
    }
}
