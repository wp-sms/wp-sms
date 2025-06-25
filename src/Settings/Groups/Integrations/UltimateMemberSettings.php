<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Notification\NotificationFactory;

class UltimateMemberSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'ultimate_member';
    }

    public function getLabel(): string
    {
        return 'Ultimate Member Integration Settings';
    }

    public function isAvailable(): bool
    {
        return function_exists('um_user');
    }

    public function getFields(): array
    {
        if (! $this->isAvailable()) {
            return [
                new Field([
                    'key'         => 'um_notify_form',
                    'type'        => 'notice',
                    'label'       => 'Not active',
                    'description' => 'Ultimate Member plugin should be enabled to run this tab',
                    'group_label' => 'Ultimate Member',
                ])
            ];
        }

        return [
            new Field([
                'key'         => 'um_notification_header',
                'type'        => 'header',
                'label'       => 'Notification',
                'description' => 'Section heading for approval notifications',
                'group_label' => 'Ultimate Member',
            ]),
            new Field([
                'key'         => 'um_send_sms_after_approval',
                'type'        => 'checkbox',
                'label'       => 'Send SMS after approval',
                'description' => 'Send SMS to user after they are approved in Ultimate Member',
                'group_label' => 'Ultimate Member',
            ]),
            new Field([
                'key'         => 'um_message_body',
                'type'        => 'textarea',
                'label'       => 'Message body',
                'description' => 'SMS message content. Variables: <code>%user_login%</code>, <code>%user_email%</code>, <code>%display_name%</code>, etc.<br>' . NotificationFactory::getUser()->printVariables(),
                'group_label' => 'Ultimate Member',
            ]),
        ];
    }
}
