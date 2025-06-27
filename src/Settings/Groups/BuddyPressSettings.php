<?php

namespace WP_SMS\Settings\Groups;

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

    public function getIcon(): string
    {
        return LucideIcons::USERS;
    }

    public function getSections(): array
    {
        if (!class_exists('BuddyPress')) {
            return [
                new Section([
                    'id' => 'buddypress_not_active',
                    'title' => __('Not active', 'wp-sms'),
                    'subtitle' => __('BuddyPress plugin should be installed to show the options.', 'wp-sms'),
                    'fields' => []
                ])
            ];
        }

        return [
            new Section([
                'id' => 'welcome_notification',
                'title' => __('Welcome Notification', 'wp-sms'),
                'subtitle' => __('By enabling this option you can send welcome SMS to new BuddyPress users', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'bp_welcome_notification_enable',
                        'label' => __('Status', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send an SMS to user when register on BuddyPress.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'bp_welcome_notification_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: User login, %2$s: User email, %3$s: User display name
                                __('User login: %1$s, User email: %2$s, User display name: %3$s', 'wp-sms'),
                                '<code>%user_login%</code>',
                                '<code>%user_email%</code>',
                                '<code>%display_name%</code>'
                            )
                    ]),
                ]
            ]),
            new Section([
                'id' => 'mention_notification',
                'title' => __('Mention Notification', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for user mentions', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'bp_mention_enable',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to user when someone mentioned. for example @admin', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'bp_mention_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Display name, %2$s: Profile link, %3$s: Time, %4$s: Message, %5$s: Receiver display name
                                __('Posted user display name: %1$s, User profile permalink: %2$s, Time: %3$s, Message: %4$s, Receiver user display name: %5$s', 'wp-sms'),
                                '<code>%posted_user_display_name%</code>',
                                '<code>%primary_link%</code>',
                                '<code>%time%</code>',
                                '<code>%message%</code>',
                                '<code>%receiver_user_display_name%</code>'
                            )
                    ]),
                ]
            ]),
            new Section([
                'id' => 'private_message_notification',
                'title' => __('Private Message Notification', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for private messages', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'bp_private_message_enable',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS notification when user received a private message', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'bp_private_message_content',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Sender name, %2$s: Subject, %3$s: Message, %4$s: Message URL
                                __('Sender display name: %1$s, Subject: %2$s, Message: %3$s, Message URL: %4$s', 'wp-sms'),
                                '<code>%sender_display_name%</code>',
                                '<code>%subject%</code>',
                                '<code>%message%</code>',
                                '<code>%message_url%</code>'
                            )
                    ]),
                ]
            ]),
            new Section([
                'id' => 'user_activity_comments',
                'title' => __('User activity comments', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for activity comment replies', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'bp_comments_activity_enable',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to user when the user get a reply on activity', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'bp_comments_activity_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Display name, %2$s: Comment, %3$s: Receiver name
                                __('Posted user display name: %1$s, Comment content: %2$s, Receiver user display name: %3$s', 'wp-sms'),
                                '<code>%posted_user_display_name%</code>',
                                '<code>%comment%</code>',
                                '<code>%receiver_user_display_name%</code>'
                            )
                    ]),
                ]
            ]),
            new Section([
                'id' => 'user_reply_comments',
                'title' => __('User reply comments', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for comment replies', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'bp_comments_reply_enable',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to user when the user get a reply on comment', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'bp_comments_reply_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Display name, %2$s: Comment, %3$s: Receiver name
                                __('Posted user display name: %1$s, Comment content: %2$s, Receiver user display name: %3$s', 'wp-sms'),
                                '<code>%posted_user_display_name%</code>',
                                '<code>%comment%</code>',
                                '<code>%receiver_user_display_name%</code>'
                            )
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