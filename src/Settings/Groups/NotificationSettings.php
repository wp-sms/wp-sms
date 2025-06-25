<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Helper;
use WP_SMS\Newsletter;
use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
class NotificationSettings extends AbstractSettingGroup {
    public function getName(): string {
        return 'notifications';
    }

    public function getLabel(): string {
        return 'Notifications Settings';
    }

    public function getFields(): array {
        return [
            new Field([
                'key' => 'notif_publish_new_post_title',
                'type' => 'header',
                'label' => 'New Post Alerts',
                'description' => 'Configure SMS notifications for new published posts.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_post',
                'type' => 'checkbox',
                'label' => 'Status',
                'description' => 'Send SMS for new posts.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_post_type',
                'type' => 'multiselect',
                'label' => 'Post Types',
                'description' => 'Select post types that trigger notifications.',
                'options' => $this->getListPostType(['show_ui' => 1]),
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_taxonomy_and_term',
                'type' => 'advancedmultiselect',
                'label' => 'Taxonomies and Terms',
                'description' => 'Choose categories or tags to associate with alerts.',
                'options' => $this->getTaxonomiesAndTerms(),
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_post_receiver',
                'type' => 'select',
                'label' => 'Notification Recipients',
                'description' => 'Select who receives notifications.',
                'options' => [
                    'subscriber' => 'Subscribers',
                    'numbers' => 'Individual Numbers',
                    'users' => 'User Roles',
                ],
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_post_default_group',
                'type' => 'select',
                'label' => 'Subscribe Group',
                'description' => 'Set the default subscriber group for notifications.',
                'show_if' => ['notif_publish_new_post_receiver' => 'subscriber'],
                'options' => $this->getSubscriberGroups(),
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_post_users',
                'type' => 'multiselect',
                'label' => 'Specific Roles',
                'description' => 'Assign notifications to WordPress user roles.',
                'show_if' => ['notif_publish_new_post_receiver' => 'users'],
                'options' => $this->getRoles(),
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_post_numbers',
                'type' => 'text',
                'label' => 'Individual Numbers',
                'description' => 'Enter comma-separated numbers to receive alerts.',
                'show_if' => ['notif_publish_new_post_receiver' => 'numbers'],
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_post_force',
                'type' => 'checkbox',
                'label' => 'Force Send',
                'description' => 'Force sending SMS without confirmation, supports REST API.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_send_mms',
                'type' => 'checkbox',
                'label' => 'Send MMS',
                'description' => 'Sends featured image as MMS if supported by the gateway.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_post_template',
                'type' => 'textarea',
                'label' => 'Message Body',
                'description' => esc_html__('Define the SMS format.', 'wp-sms') . '<br>' . NotificationFactory::getPost()->printVariables(),
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_post_words_count',
                'type' => 'number',
                'label' => 'Post Content Words Limit',
                'description' => 'Max word count for post excerpt. Default: 10.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_post_author_title',
                'type' => 'header',
                'label' => 'Post Author Notification',
                'description' => 'Notify post authors upon publishing.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_post_author',
                'type' => 'checkbox',
                'label' => 'Status',
                'description' => 'Enable SMS to authors after post is published.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_post_author_post_type',
                'type' => 'multiselect',
                'label' => 'Post Types',
                'description' => 'Define which post types trigger author notifications.',
                'options' => $this->getListPostType(['show_ui' => 1]),
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_post_author_template',
                'type' => 'textarea',
                'label' => 'Message Body',
                'description' => esc_html__('Customize the SMS message to authors using placeholders for post details.', 'wp-sms') . '<br>' . NotificationFactory::getPost()->printVariables(),
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_wpversion_title',
                'type' => 'header',
                'label' => 'The new release of WordPress',
                'description' => 'Notify admin of new WordPress versions.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_publish_new_wpversion',
                'type' => 'checkbox',
                'label' => 'Status',
                'description' => 'Enable SMS for new WP releases.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_register_new_user_title',
                'type' => 'header',
                'label' => 'Register a new user',
                'description' => 'Setup SMS for user and admin on new user registration.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_register_new_user',
                'type' => 'checkbox',
                'label' => 'Status',
                'description' => 'Enable SMS notifications for user registration.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_register_new_user_admin_template',
                'type' => 'textarea',
                'label' => 'Message Body for Admin',
                'description' => esc_html__('Customize the SMS template sent to the Admin Mobile Number for new user registrations using placeholders for user details.', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables(),
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_register_new_user_template',
                'type' => 'textarea',
                'label' => 'Message Body for User',
                'description' => esc_html__('Customize the SMS template sent to the user upon registration using placeholders for personal details.', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables(),
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_new_comment_title',
                'type' => 'header',
                'label' => 'New Comment Notification',
                'description' => 'Notify admin when new comment is posted.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_new_comment',
                'type' => 'checkbox',
                'label' => 'Status',
                'description' => 'Enable SMS alerts for new comments.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_new_comment_template',
                'type' => 'textarea',
                'label' => 'Message Body',
                'description' => esc_html__('Create the SMS message for new comment alerts. Include details using placeholders:', 'wp-sms') . '<br>' . NotificationFactory::getComment()->printVariables(),
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_user_login_title',
                'type' => 'header',
                'label' => 'User Login Notification',
                'description' => 'Notify admin on user login.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_user_login',
                'type' => 'checkbox',
                'label' => 'Status',
                'description' => 'Enable SMS on user login.',
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_user_login_roles',
                'type' => 'multiselect',
                'label' => 'Specific Roles',
                'description' => 'Choose which roles trigger login SMS.',
                'options' => $this->getRoles(),
                'group_label' => 'Notifications',
            ]),
            new Field([
                'key' => 'notif_user_login_template',
                'type' => 'textarea',
                'label' => 'Message Body',
                'description' => esc_html__('Format the SMS message sent upon user login. Utilize placeholders to include user details:', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables(),
                'group_label' => 'Notifications',
            ]),
        ];
    }
}
