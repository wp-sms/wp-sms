<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Notification\NotificationFactory;

class AwesomeSupportSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'awesome_support';
    }

    public function getLabel(): string
    {
        return __('Awesome Support', 'wp-sms');
    }

    public function getIcon(): string
    {
        return LucideIcons::HELP_CIRCLE;
    }

    public function getSections(): array
    {
        if (!class_exists('Awesome_Support')) {
            return [
                new Section([
                    'id' => 'awesome_support_not_active',
                    'title' => __('Not active', 'wp-sms'),
                    'subtitle' => __('Awesome Support plugin should be installed to show the options.', 'wp-sms'),
                    'fields' => []
                ])
            ];
        }

        return [
            new Section([
                'id' => 'new_ticket_notification',
                'title' => __('Notify for new ticket', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for new support ticket submissions', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'as_notify_open_ticket_status',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to admin when the user opened a new ticket.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'as_notify_open_ticket_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'admin_reply_notification',
                'title' => __('Notify admin for get reply', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for admin when users reply to tickets', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'as_notify_admin_reply_ticket_status',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to admin when the user replied the ticket.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'as_notify_admin_reply_ticket_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'user_reply_notification',
                'title' => __('Notify user for get reply', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for users when admins reply to tickets', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'as_notify_user_reply_ticket_status',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to user when the admin replied the ticket. Please make sure the "Add Mobile number field" option is enabled in the Settings > Features', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'as_notify_user_reply_ticket_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'ticket_status_update_notification',
                'title' => __('Notify user for the ticket status update', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for users when ticket status changes', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'as_notify_update_ticket_status',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to user when the ticket status updates', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'as_notify_update_ticket_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'ticket_close_notification',
                'title' => __('Notify user when the ticket is closed', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for users when tickets are closed', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'as_notify_close_ticket_status',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to user when the ticket is closed', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'as_notify_close_ticket_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables()
                    ]),
                ]
            ]),
        ];
    }

    public function getFields(): array
    {
        // Legacy method - return all fields from all sections for backward compatibility
        $allFields = [];
        foreach ($this->getSections() as $section) {
            $allFields = array_merge($allFields, $section->getFields());
        }
        return $allFields;
    }
} 