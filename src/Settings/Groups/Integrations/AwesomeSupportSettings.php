<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Notification\NotificationFactory;

class AwesomeSupportSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'awesome_support';
    }

    public function getLabel(): string
    {
        return 'Awesome Support Integration Settings';
    }

    public function isAvailable(): bool
    {
        return class_exists('Awesome_Support');
    }

    public function getFields(): array
    {
        if (! $this->isAvailable()) {
            return [
                new Field([
                    'key'         => 'as_notify_new_ticket',
                    'type'        => 'notice',
                    'label'       => 'Not active',
                    'description' => 'Awesome Support plugin should be installed to show the options.',
                    'group_label' => 'Awesome Support',
                ])
            ];
        }

        return [
            new Field([
                'key'         => 'as_notify_new_ticket',
                'type'        => 'header',
                'label'       => 'Notify for new ticket',
                'group_label' => 'Awesome Support',
            ]),
            new Field([
                'key'         => 'as_notify_open_ticket_status',
                'type'        => 'checkbox',
                'label'       => 'Send SMS (New Ticket)',
                'description' => 'Send SMS to admin when a user opens a new ticket',
                'group_label' => 'Awesome Support',
            ]),
            new Field([
                'key'         => 'as_notify_open_ticket_message',
                'type'        => 'textarea',
                'label'       => 'Message body (New Ticket)',
                'description' => 'SMS content. Supports Awesome Support placeholders.<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables(),
                'group_label' => 'Awesome Support',
            ]),

            new Field([
                'key'         => 'as_notify_admin_reply_ticket',
                'type'        => 'header',
                'label'       => 'Notify admin for get reply',
                'group_label' => 'Awesome Support',
            ]),
            new Field([
                'key'         => 'as_notify_admin_reply_ticket_status',
                'type'        => 'checkbox',
                'label'       => 'Send SMS (User Reply to Ticket)',
                'description' => 'Send SMS to admin when the user replies to a ticket',
                'group_label' => 'Awesome Support',
            ]),
            new Field([
                'key'         => 'as_notify_admin_reply_ticket_message',
                'type'        => 'textarea',
                'label'       => 'Message body (User Reply to Ticket)',
                'description' => 'SMS content. Supports Awesome Support placeholders.<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables(),
                'group_label' => 'Awesome Support',
            ]),

            new Field([
                'key'         => 'as_notify_user_reply_ticket',
                'type'        => 'header',
                'label'       => 'Notify user for get reply',
                'group_label' => 'Awesome Support',
            ]),
            new Field([
                'key'         => 'as_notify_user_reply_ticket_status',
                'type'        => 'checkbox',
                'label'       => 'Send SMS (Admin Reply to Ticket)',
                'description' => 'Send SMS to user when admin replies (requires mobile field enabled)',
                'group_label' => 'Awesome Support',
            ]),
            new Field([
                'key'         => 'as_notify_user_reply_ticket_message',
                'type'        => 'textarea',
                'label'       => 'Message body (Admin Reply to Ticket)',
                'description' => 'SMS content. Supports Awesome Support placeholders.<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables(),
                'group_label' => 'Awesome Support',
            ]),

            new Field([
                'key'         => 'as_notify_update_ticket',
                'type'        => 'header',
                'label'       => 'Notify user for the ticket status update',
                'group_label' => 'Awesome Support',
            ]),
            new Field([
                'key'         => 'as_notify_update_ticket_status',
                'type'        => 'checkbox',
                'label'       => 'Send SMS (Ticket Status Update)',
                'description' => 'Send SMS to user when ticket status is updated',
                'group_label' => 'Awesome Support',
            ]),
            new Field([
                'key'         => 'as_notify_update_ticket_message',
                'type'        => 'textarea',
                'label'       => 'Message body (Ticket Status Update)',
                'description' => 'SMS content. Supports Awesome Support placeholders.<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables(),
                'group_label' => 'Awesome Support',
            ]),

            new Field([
                'key'         => 'as_notify_close_ticket',
                'type'        => 'header',
                'label'       => 'Notify user when the ticket is closed',
                'group_label' => 'Awesome Support',
            ]),
            new Field([
                'key'         => 'as_notify_close_ticket_status',
                'type'        => 'checkbox',
                'label'       => 'Send SMS (Ticket Closed)',
                'description' => 'Send SMS to user when ticket is closed',
                'group_label' => 'Awesome Support',
            ]),
            new Field([
                'key'         => 'as_notify_close_ticket_message',
                'type'        => 'textarea',
                'label'       => 'Message body (Ticket Closed)',
                'description' => 'SMS content. Supports Awesome Support placeholders.<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables(),
                'group_label' => 'Awesome Support',
            ]),
        ];
    }
}