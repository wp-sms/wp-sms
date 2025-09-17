<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;

class BuddyPressSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'buddypress';
    }

    public function getLabel(): string
    {
        return __('BuddyPress', 'wp-sms');
    }

    public function getSections(): array
    {
        $isPluginActive = class_exists('BuddyPress');
        $sections = [];

        // Always show plugin status notice first when plugin is inactive
        if (!$isPluginActive) {
            $sections[] = new Section([
                'id' => 'buddypress_integration',
                'title' => __('BuddyPress Integration', 'wp-sms'),
                'subtitle' => __('Connect SMS alerts to BuddyPress activities', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'buddypress_not_active_notice',
                        'label' => __('Not active', 'wp-sms'),
                        'type' => 'notice',
                        'description' => __('Install and activate the BuddyPress plugin to see these options.', 'wp-sms')
                    ])
                ]
            ]);
        }

        $sections[] = new Section([
            'id' => 'welcome_notification',
            'title' => __('Welcome Notification', 'wp-sms'),
            'subtitle' => __('Send a welcome SMS to new BuddyPress users', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'bp_welcome_notification_enable',
                    'label' => __('Enable', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send an SMS to users when they register in BuddyPress.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'bp_welcome_notification_message',
                    'label' => __('SMS Template', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Write the SMS text. You can use placeholders: %user_login%, %user_email%, %display_name%.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        $sections[] = new Section([
            'id' => 'mention_notification',
            'title' => __('Mention Notification', 'wp-sms'),
            'subtitle' => __('Alert users when they are mentioned', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'bp_mention_enable',
                    'label' => __('Enable', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send an SMS when a user is mentioned, for example @admin.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'bp_mention_message',
                    'label' => __('SMS Template', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Write the SMS text. You can use placeholders: %posted_user_display_name%, %primary_link%, %time%, %message%, %receiver_user_display_name%.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        $sections[] = new Section([
            'id' => 'private_message_notification',
            'title' => __('Private Message Notification', 'wp-sms'),
            'subtitle' => __('Alert users about new private messages', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'bp_private_message_enable',
                    'label' => __('Enable', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send an SMS when a user receives a private message.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'bp_private_message_content',
                    'label' => __('SMS Template', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Write the SMS text. You can use placeholders: %sender_display_name%, %subject%, %message%, %message_url%.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        $sections[] = new Section([
            'id' => 'user_activity_comments',
            'title' => __('Activity Replies', 'wp-sms'),
            'subtitle' => __('Notify users when someone replies to their activity', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'bp_comments_activity_enable',
                    'label' => __('Enable', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send an SMS when a user receives a reply on an activity update.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'bp_comments_activity_message',
                    'label' => __('SMS Template', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Write the SMS text. You can use placeholders: %posted_user_display_name%, %comment%, %receiver_user_display_name%.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        $sections[] = new Section([
            'id' => 'user_reply_comments',
            'title' => __('Comment Replies', 'wp-sms'),
            'subtitle' => __('Notify users when someone replies to their comment', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'bp_comments_reply_enable',
                    'label' => __('Enable', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send an SMS when a user receives a reply on a comment.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'bp_comments_reply_message',
                    'label' => __('SMS Template', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Write the SMS text. You can use placeholders: %posted_user_display_name%, %comment%, %receiver_user_display_name%.', 'wp-sms'),
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