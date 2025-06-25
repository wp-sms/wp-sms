<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;

class BuddyPressSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'buddypress';
    }

    public function getLabel(): string
    {
        return 'BuddyPress Integration Settings';
    }

    public function isAvailable(): bool
    {
        return class_exists('BuddyPress');
    }

    public function getFields(): array
    {
        if (! $this->isAvailable()) {
            return [
                new Field([
                    'key'         => 'bp_fields',
                    'type'        => 'notice',
                    'label'       => 'Not active',
                    'description' => 'BuddyPress plugin should be installed to show the options.',
                    'group_label' => 'BuddyPress',
                ])
            ];
        }

        return [
            new Field([
                'key'         => 'bp_welcome_notification',
                'type'        => 'header',
                'label'       => 'Welcome Notification',
                'description' => 'Enable SMS to new BuddyPress users',
                'group_label' => 'BuddyPress',
            ]),
            new Field([
                'key'         => 'bp_welcome_notification_enable',
                'type'        => 'checkbox',
                'label'       => 'Status',
                'description' => 'Send SMS on BuddyPress user registration',
                'group_label' => 'BuddyPress',
            ]),
            new Field([
                'key'         => 'bp_welcome_notification_message',
                'type'        => 'textarea',
                'label'       => 'Message body',
                'description' => 'Message with placeholders: <code>%user_login%</code>, <code>%user_email%</code>, <code>%display_name%</code>',
                'group_label' => 'BuddyPress',
            ]),

            // Mention
            new Field([
                'key'         => 'mentions',
                'type'        => 'header',
                'label'       => 'Mention Notification',
                'group_label' => 'BuddyPress',
            ]),
            new Field([
                'key'         => 'bp_mention_enable',
                'type'        => 'checkbox',
                'label'       => 'Send SMS',
                'description' => 'Send SMS when user is mentioned (e.g. @username)',
                'group_label' => 'BuddyPress',
            ]),
            new Field([
                'key'         => 'bp_mention_message',
                'type'        => 'textarea',
                'label'       => 'Message body',
                'description' => 'Placeholders: <code>%posted_user_display_name%</code>, <code>%primary_link%</code>, <code>%time%</code>, <code>%message%</code>, <code>%receiver_user_display_name%</code>',
                'group_label' => 'BuddyPress',
            ]),

            // Private Message
            new Field([
                'key'         => 'private_message',
                'type'        => 'header',
                'label'       => 'Private Message Notification',
                'group_label' => 'BuddyPress',
            ]),
            new Field([
                'key'         => 'bp_private_message_enable',
                'type'        => 'checkbox',
                'label'       => 'Send SMS',
                'description' => 'Send SMS when user receives a private message',
                'group_label' => 'BuddyPress',
            ]),
            new Field([
                'key'         => 'bp_private_message_content',
                'type'        => 'textarea',
                'label'       => 'Message body',
                'description' => 'Placeholders: <code>%sender_display_name%</code>, <code>%subject%</code>, <code>%message%</code>, <code>%message_url%</code>',
                'group_label' => 'BuddyPress',
            ]),

            // Activity Replies
            new Field([
                'key'         => 'comments_activity',
                'type'        => 'header',
                'label'       => 'User Activity Comments',
                'group_label' => 'BuddyPress',
            ]),
            new Field([
                'key'         => 'bp_comments_activity_enable',
                'type'        => 'checkbox',
                'label'       => 'Send SMS',
                'description' => 'Send SMS on activity comment reply',
                'group_label' => 'BuddyPress',
            ]),
            new Field([
                'key'         => 'bp_comments_activity_message',
                'type'        => 'textarea',
                'label'       => 'Message body',
                'description' => 'Placeholders: <code>%posted_user_display_name%</code>, <code>%comment%</code>, <code>%receiver_user_display_name%</code>',
                'group_label' => 'BuddyPress',
            ]),

            // Comment Replies
            new Field([
                'key'         => 'comments',
                'type'        => 'header',
                'label'       => 'User Reply Comments',
                'group_label' => 'BuddyPress',
            ]),
            new Field([
                'key'         => 'bp_comments_reply_enable',
                'type'        => 'checkbox',
                'label'       => 'Send SMS',
                'description' => 'Send SMS when a comment gets a reply',
                'group_label' => 'BuddyPress',
            ]),
            new Field([
                'key'         => 'bp_comments_reply_message',
                'type'        => 'textarea',
                'label'       => 'Message body',
                'description' => 'Placeholders: <code>%posted_user_display_name%</code>, <code>%comment%</code>, <code>%receiver_user_display_name%</code>',
                'group_label' => 'BuddyPress',
            ]),
        ];
    }
}
