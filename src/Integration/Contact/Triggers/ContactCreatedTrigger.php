<?php

namespace WSms\Integration\Contact\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class ContactCreatedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'wsms.contact_created';
    }

    public function getName(): string
    {
        return __('Contact Created', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a new contact is created', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WSMS';
    }

    public function getPayloadSchema(): array
    {
        return [
            'contact_id' => [
                'type'        => 'string',
                'label'       => __('Contact ID', 'wp-sms'),
                'description' => __('The ULID of the newly created contact', 'wp-sms'),
                'example'     => '01HY0000000000000000000000',
            ],
            'email' => [
                'type'        => 'string',
                'label'       => __('Email', 'wp-sms'),
                'description' => __('Email address of the contact', 'wp-sms'),
                'example'     => 'contact@example.com',
            ],
            'phone' => [
                'type'        => 'string',
                'label'       => __('Phone', 'wp-sms'),
                'description' => __('Phone number of the contact', 'wp-sms'),
                'example'     => '+15551234567',
            ],
            'first_name' => [
                'type'        => 'string',
                'label'       => __('First Name', 'wp-sms'),
                'description' => __('First name of the contact', 'wp-sms'),
                'example'     => 'John',
            ],
            'last_name' => [
                'type'        => 'string',
                'label'       => __('Last Name', 'wp-sms'),
                'description' => __('Last name of the contact', 'wp-sms'),
                'example'     => 'Doe',
            ],
            'source' => [
                'type'        => 'string',
                'label'       => __('Source', 'wp-sms'),
                'description' => __('How the contact was created (manual, import, sync, flow)', 'wp-sms'),
                'example'     => 'manual',
            ],
        ];
    }

    public function getFilterSchema(): array
    {
        return [
            'source' => [
                'type'        => 'string',
                'label'       => __('Source', 'wp-sms'),
                'description' => __('Only trigger for contacts from this source', 'wp-sms'),
                'enum'        => ['manual', 'import', 'sync', 'flow', 'api'],
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_contact_created', function (string $contactId, array $data) use ($callback) {
            $callback([
                'contact_id' => $contactId,
                'email'      => $data['email'] ?? '',
                'phone'      => $data['phone'] ?? '',
                'first_name' => $data['first_name'] ?? '',
                'last_name'  => $data['last_name'] ?? '',
                'source'     => $data['source'] ?? 'manual',
            ]);
        }, 10, 2);
    }
}
