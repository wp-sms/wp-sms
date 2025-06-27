<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Helper;
use WP_SMS\Newsletter;
use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Settings\Tags;

class NotificationSettings extends AbstractSettingGroup {
    public function getName(): string {
        return 'notifications';
    }

    public function getLabel(): string {
        return __('Notifications', 'wp-sms');
    }

    public function getIcon(): string {
        return LucideIcons::BELL;
    }

    public function getSections(): array {
        return [
            new Section([
                'id' => 'new_post_alerts',
                'title' => __('New Post Alerts', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications to inform subscribers about newly published content.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'notif_publish_new_post',
                        'label' => __('Status', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS for new posts.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_type',
                        'label' => __('Post Types', 'wp-sms'),
                        'type' => 'multiselect',
                        'options' => $this->getPostTypes(),
                        'description' => __('Specify which types of content trigger notifications.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_taxonomy_and_term',
                        'label' => __('Taxonomies and Terms', 'wp-sms'),
                        'type' => 'advancedmultiselect',
                        'options' => $this->getTaxonomiesAndTerms(),
                        'description' => __('Choose categories or tags to associate with alerts.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_receiver',
                        'label' => __('Notification Recipients', 'wp-sms'),
                        'type' => 'select',
                        'options' => [
                            'subscriber' => __('Subscribers', 'wp-sms'),
                            'numbers' => __('Individual Numbers', 'wp-sms'),
                            'users' => __('User Roles', 'wp-sms')
                        ],
                        'description' => __('Select who receives notifications.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_default_group',
                        'label' => __('Subscribe Group', 'wp-sms'),
                        'type' => 'select',
                        'options' => $this->getSubscribeGroups(),
                        'description' => __('Set the default group to receive notifications.', 'wp-sms'),
                        'show_if' => ['notif_publish_new_post_receiver' => 'subscriber']
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_users',
                        'label' => __('Specific Roles', 'wp-sms'),
                        'type' => 'multiselect',
                        'options' => $this->getRoles(),
                        'description' => __('Assign SMS alerts to specific WordPress user roles.', 'wp-sms'),
                        'show_if' => ['notif_publish_new_post_receiver' => 'users']
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_numbers',
                        'label' => __('Individual Numbers', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter mobile number(s) here to receive SMS alerts. For multiple numbers, separate them with commas.', 'wp-sms'),
                        'show_if' => ['notif_publish_new_post_receiver' => 'numbers']
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_force',
                        'label' => __('Force Send', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Use to send notifications without additional confirmation during publishing. Compatible with WP-REST API.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_send_mms',
                        'label' => __('Send MMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Sends the featured image of the post as an MMS if supported by your SMS gateway.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_template',
                        'label' => __('Message Body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Define the SMS format.', 'wp-sms') . '<br>' . NotificationFactory::getPost()->printVariables()
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_words_count',
                        'label' => __('Post Content Words Limit', 'wp-sms'),
                        'type' => 'number',
                        'description' => __('Set maximum word count for post excerpts in notifications. Default: 10.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'post_author_notification',
                'title' => __('Post Author Notification', 'wp-sms'),
                'subtitle' => __('Set up notifications for post authors when their content is published. Ensure the mobile number field is added to user profiles under Settings > General > Mobile Number Field Source.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'notif_publish_new_post_author',
                        'label' => __('Status', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Alerts post authors via SMS after publishing their posts.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_author_post_type',
                        'label' => __('Post Types', 'wp-sms'),
                        'type' => 'multiselect',
                        'options' => $this->getPostTypes(),
                        'description' => __('Define which content types trigger author notifications.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_author_template',
                        'label' => __('Message Body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Customize the SMS message to authors using placeholders for post details.', 'wp-sms') . '<br>' . NotificationFactory::getPost()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'wordpress_version_notification',
                'title' => __('The new release of WordPress', 'wp-sms'),
                'subtitle' => __('Configure notifications to be sent via SMS to the Admin Mobile Number regarding new releases of WordPress.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'notif_publish_new_wpversion',
                        'label' => __('Status', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Notifications for new WordPress releases.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'new_user_registration',
                'title' => __('Register a new user', 'wp-sms'),
                'subtitle' => __('Set up SMS notifications for admin and new user upon registration.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'notif_register_new_user',
                        'label' => __('Status', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('SMS notifications for new user registrations.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_register_new_user_admin_template',
                        'label' => __('Message Body for Admin', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Customize the SMS template sent to the Admin Mobile Number for new user registrations using placeholders for user details.', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables()
                    ]),
                    new Field([
                        'key' => 'notif_register_new_user_template',
                        'label' => __('Message Body for User', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Customize the SMS template sent to the user upon registration using placeholders for personal details.', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'new_comment_notification',
                'title' => __('New Comment Notification', 'wp-sms'),
                'subtitle' => __('Receive SMS alerts on the Admin Mobile Number when a new comment is posted.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'notif_new_comment',
                        'label' => __('Status', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Receiving SMS alerts on the Admin Mobile Number for each new comment.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_new_comment_template',
                        'label' => __('Message Body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Create the SMS message for new comment alerts. Include details using placeholders:', 'wp-sms') . '<br>' . NotificationFactory::getComment()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'user_login_notification',
                'title' => __('User Login Notification', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications to be sent to the Admin Mobile Number whenever a user logs in.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'notif_user_login',
                        'label' => __('Status', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('SMS notifications to be sent to the Admin Mobile Number on user login.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_user_login_roles',
                        'label' => __('Specific Roles', 'wp-sms'),
                        'type' => 'multiselect',
                        'options' => $this->getRoles(),
                        'description' => __('Choose user roles that trigger login notifications.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_user_login_template',
                        'label' => __('Message Body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Format the SMS message sent upon user login. Utilize placeholders to include user details:', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables()
                    ]),
                ]
            ]),
        ];
    }

    public function getPostTypes(): array {
        $postTypes = get_post_types(['show_ui' => 1], 'objects');
        $options = [];
        
        foreach ($postTypes as $postType) {
            $options[$postType->name] = $postType->labels->name;
        }
        
        return $options;
    }

    public function getTaxonomiesAndTerms(): array {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $options = [];
        
        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy->name,
                'hide_empty' => false,
            ]);
            
            if (!empty($terms) && !is_wp_error($terms)) {
                $options[$taxonomy->name] = [
                    'label' => $taxonomy->labels->name,
                    'options' => []
                ];
                
                foreach ($terms as $term) {
                    $options[$taxonomy->name]['options'][$term->term_id] = $term->name;
                }
            }
        }
        
        return $options;
    }

    private function getSubscribeGroups(): array {
        global $wpdb;
        
        $groups = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sms_subscribes_group");
        $options = [];
        
        if ($groups) {
            foreach ($groups as $group) {
                $options[$group->ID] = $group->name;
            }
        }
        
        return $options;
    }

    public function getRoles(): array {
        $roles = wp_roles()->get_names();
        return $roles;
    }

    public function getFields(): array {
        // Legacy method - return all fields from all sections for backward compatibility
        $allFields = [];
        foreach ($this->getSections() as $section) {
            $allFields = array_merge($allFields, $section->getFields());
        }
        return $allFields;
    }
}
