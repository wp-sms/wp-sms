<?php

namespace WSms\Integration\Contact\Triggers;

use WSms\Enums\ContactStatus;
use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class ContactStatusChangedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'wsms.contact_status_changed';
    }

    public function getName(): string
    {
        return __('Contact Status Changed', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a contact\'s subscription status changes', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('WSMS', 'wp-sms');
    }

    public function getPayloadSchema(): array
    {
        return [
            'contact_id' => [
                'type'        => 'string',
                'label'       => __('Contact ID', 'wp-sms'),
                'description' => __('The ULID of the contact', 'wp-sms'),
                'example'     => '01HY0000000000000000000000',
            ],
            'old_status' => [
                'type'        => 'string',
                'label'       => __('Previous Status', 'wp-sms'),
                'description' => __('The status before the change', 'wp-sms'),
                'example'     => 'subscribed',
            ],
            'new_status' => [
                'type'        => 'string',
                'label'       => __('New Status', 'wp-sms'),
                'description' => __('The status after the change', 'wp-sms'),
                'example'     => 'bounced',
            ],
        ];
    }

    public function getFilterSchema(): array
    {
        $statuses = array_map(fn(ContactStatus $s) => $s->value, ContactStatus::cases());

        return [
            'new_status' => [
                'type'        => 'string',
                'label'       => __('New Status', 'wp-sms'),
                'description' => __('Only trigger when status changes to this value', 'wp-sms'),
                'enum'        => $statuses,
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_contact_status_changed', function (string $contactId, string $oldStatus, string $newStatus) use ($callback) {
            $callback([
                'contact_id' => $contactId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
        }, 10, 3);
    }
}
