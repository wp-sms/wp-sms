<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Notification\NotificationFactory;

class UltimateMemberSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'ultimate_member';
    }

    public function getLabel(): string
    {
        return __('Ultimate Member', 'wp-sms');
    }

    public function getIcon(): string
    {
        return LucideIcons::USER_CHECK;
    }

    public function getSections(): array
    {
        if (!function_exists('um_user')) {
            return [
                new Section([
                    'id' => 'ultimate_member_not_active',
                    'title' => __('Not active', 'wp-sms'),
                    'subtitle' => __('Ultimate Member plugin should be enable to run this tab', 'wp-sms'),
                    'fields' => []
                ])
            ];
        }

        return [
            new Section([
                'id' => 'user_approval_notification',
                'title' => __('Notification', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for Ultimate Member user approval', 'wp-sms'),
                'help_url' => '/resources/ultimate-member-and-wp-sms-integration/',
                'fields' => [
                    new Field([
                        'key' => 'um_send_sms_after_approval',
                        'label' => __('Send SMS after approval', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS after the user is approved', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'um_message_body',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables()
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