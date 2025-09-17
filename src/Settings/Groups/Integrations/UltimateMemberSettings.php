<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
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

    public function getSections(): array
    {
        $isPluginActive = function_exists('um_user');

        $sections = [];

        if (!$isPluginActive) {
            $sections[] = new Section([
                'id' => 'ultimate_member_not_active',
                'type' => 'notice',
                'title' => __('Integration inactive', 'wp-sms'),
                'subtitle' => __('Activate the Ultimate Member plugin to use these settings.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'ultimate_member_not_active_notice',
                        'label' => __('Not active', 'wp-sms'),
                        'type' => 'notice',
                        'description' => __('Install and activate the Ultimate Member plugin to see these options.', 'wp-sms')
                    ])
                ]
            ]);
        }

        $sections[] = new Section([
            'id' => 'user_approval_notification',
            'title' => __('User approval SMS', 'wp-sms'),
            'subtitle' => __('Send an SMS to the user after their account is approved in Ultimate Member.', 'wp-sms'),
            'help_url' => WP_SMS_SITE . '/resources/ultimate-member-and-wp-sms-integration/',
            'fields' => [
                new Field([
                    'key' => 'um_send_sms_after_approval',
                    'label' => __('Send SMS on approval', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('When enabled, a text message is sent after the user is approved.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'um_message_body',
                    'label' => __('Message template', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Write the SMS content. You can use the variables shown below. Keep it short for SMS.', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables(),
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
