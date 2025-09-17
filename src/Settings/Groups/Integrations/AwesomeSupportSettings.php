<?php

namespace WP_SMS\Settings\Groups\Integrations;

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

    public function getSections(): array
    {
        $isPluginActive = class_exists('Awesome_Support');
        $sections = [];

        if (!$isPluginActive) {
            $sections[] = new Section([
                'id' => 'awesome_support_integration',
                'title' => __('Awesome Support integration', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for support tickets', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'awesome_support_not_active_notice',
                        'label' => __('Plugin not active', 'wp-sms'),
                        'type' => 'notice',
                        'description' => __('Install and activate the Awesome Support plugin to show these options.', 'wp-sms')
                    ])
                ]
            ]);
        }

        $sections[] = new Section([
            'id' => 'new_ticket_notification',
            'title' => __('New ticket', 'wp-sms'),
            'subtitle' => __('Send SMS to admins when a new ticket is created.', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'as_notify_open_ticket_status',
                    'label' => __('Enable', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send an SMS to admin recipients when a new ticket is created.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'as_notify_open_ticket_message',
                    'label' => __('Message', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Write the SMS sent to admins. Available placeholders: {printVariables}', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables(),
                    'readonly' => !$isPluginActive
                ])
            ]
        ]);
        $sections[] = new Section([
            'id' => 'admin_reply_notification',
            'title' => __('Notify admin for get reply', 'wp-sms'),
            'subtitle' => __('Configure SMS notifications for admin when users reply to tickets', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'as_notify_admin_reply_ticket_status',
                    'label' => __('Send SMS', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send SMS to admin when the user replied the ticket.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'as_notify_admin_reply_ticket_message',
                    'label' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables(),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        $sections[] = new Section([
            'id' => 'user_reply_notification',
            'title' => __('Notify user for get reply', 'wp-sms'),
            'subtitle' => __('Configure SMS notifications for users when admins reply to tickets', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'as_notify_user_reply_ticket_status',
                    'label' => __('Send SMS', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send SMS to user when the admin replied the ticket. Please make sure the "Add Mobile number field" option is enabled in the Settings > Features', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'as_notify_user_reply_ticket_message',
                    'label' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables(),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        $sections[] = new Section([
            'id' => 'ticket_status_update_notification',
            'title' => __('Notify user for the ticket status update', 'wp-sms'),
            'subtitle' => __('Configure SMS notifications for users when ticket status changes', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'as_notify_update_ticket_status',
                    'label' => __('Send SMS', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send SMS to user when the ticket status updates', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'as_notify_update_ticket_message',
                    'label' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables(),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        $sections[] = new Section([
            'id' => 'ticket_close_notification',
            'title' => __('Notify user when the ticket is closed', 'wp-sms'),
            'subtitle' => __('Configure SMS notifications for users when tickets are closed', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'as_notify_close_ticket_status',
                    'label' => __('Send SMS', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send SMS to user when the ticket is closed', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'as_notify_close_ticket_message',
                    'label' => __('Message body', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getAwesomeSupportTicket()->printVariables(),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        return $sections;
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
