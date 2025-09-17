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
                'subtitle' => __('Send an SMS when a new post is published.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'notif_publish_new_post',
                        'label' => __('Enable', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send an SMS for newly published posts.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_type',
                        'label' => __('Post Types', 'wp-sms'),
                        'type' => 'multiselect',
                        'show_if' => ['notif_publish_new_post' => true],
                        'options' => $this->getPostTypes(),
                        'description' => __('Choose which post types trigger an alert.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_taxonomy_and_term',
                        'label' => __('Categories and Tags', 'wp-sms'),
                        'type' => 'multiselect',
                        'options' => $this->getTaxonomiesAndTerms(),
                        'show_if' => ['notif_publish_new_post' => true],
                        'description' => __('Send alerts only when the post matches these terms.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_receiver',
                        'label' => __('Recipients', 'wp-sms'),
                        'type' => 'select',
                        'options' => [
                            'subscriber' => __('Subscribers', 'wp-sms'),
                            'numbers' => __('Individual Numbers', 'wp-sms'),
                            'users' => __('User Roles', 'wp-sms')
                        ],
                        'show_if' => ['notif_publish_new_post' => true],
                        'description' => __('Who should receive the alert?', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_default_group',
                        'label' => __('Subscriber Group', 'wp-sms'),
                        'type' => 'select',
                        'options' => $this->getSubscribeGroups(),
                        'description' => __('Choose the subscriber group that receives this alert.', 'wp-sms'),
                        'show_if' => ['notif_publish_new_post' => true, 'notif_publish_new_post_receiver' => 'subscriber']
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_users',
                        'label' => __('User Roles', 'wp-sms'),
                        'type' => 'multiselect',
                        'options' => $this->getRoles(),
                        'description' => __('Send to these WordPress roles.', 'wp-sms'),
                        'show_if' => ['notif_publish_new_post' => true, 'notif_publish_new_post_receiver' => 'users']
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_numbers',
                        'label' => __('Phone Numbers', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter one or more numbers separated by commas or new lines. Include country code, for example +49…', 'wp-sms'),
                        'show_if' => ['notif_publish_new_post' => true, 'notif_publish_new_post_receiver' => 'numbers']
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_force',
                        'label' => __('Auto-send on Publish', 'wp-sms'),
                        'type' => 'checkbox',
                        'show_if' => ['notif_publish_new_post' => true],
                        'description' => __('Send immediately on publish without extra confirmation. Works with WP REST API.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_send_mms',
                        'label' => __('Attach Featured Image (MMS)', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send the featured image as MMS when supported by your gateway.', 'wp-sms'),
                        'show_if' => ['notif_publish_new_post' => true],
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_template',
                        'label' => __('Message Template', 'wp-sms'),
                        'type' => 'textarea',
                        'show_if' => ['notif_publish_new_post' => true],
                        'description' => __('Write your SMS. Use the variables listed below.', 'wp-sms') . '<br>' . NotificationFactory::getPost()->printVariables()
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_words_count',
                        'label' => __('Excerpt Word Limit', 'wp-sms'),
                        'type' => 'number',
                        'show_if' => ['notif_publish_new_post' => true],
                        'description' => __('Maximum words from the post content to include. Set 0 to include none. Default: 10.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'post_author_notification',
                'title' => __('Notify Post Author', 'wp-sms'),
                'subtitle' => __('Alert the post\'s author after their content is published. Requires a mobile number in the author\'s profile. Set the source in Settings > General > Mobile Number Field Source.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'notif_publish_new_post_author',
                        'label' => __('Enable', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send an SMS to the author after publish.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_author_post_type',
                        'label' => __('Post Types', 'wp-sms'),
                        'type' => 'multiselect',
                        'options' => $this->getPostTypes(),
                        'description' => __('Choose which post types trigger an author alert.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_publish_new_post_author_template',
                        'label' => __('Message Template', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Write your SMS to the author. Use the variables listed below.', 'wp-sms') . '<br>' . NotificationFactory::getPost()->printVariables()
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
                'title' => __('New User Registration Alerts', 'wp-sms'),
                'subtitle' => __('Notify the admin and welcome the user after registration.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'notif_register_new_user',
                        'label' => __('Enable', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS alerts when a user registers.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_register_new_user_admin_template',
                        'label' => __('Message to Admin', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('SMS template sent to the Admin Mobile Number. Use the variables below.', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables()
                    ]),
                    new Field([
                        'key' => 'notif_register_new_user_template',
                        'label' => __('Message to User', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Welcome SMS sent to the new user. Use the variables below.', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'new_comment_notification',
                'title' => __('New Comment Alerts', 'wp-sms'),
                'subtitle' => __('Send an SMS to the Admin Mobile Number when a new comment is posted.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'notif_new_comment',
                        'label' => __('Enable', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send an SMS for each new comment.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_new_comment_template',
                        'label' => __('Message Template', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Write your SMS for comment alerts. Use the variables below.', 'wp-sms') . '<br>' . NotificationFactory::getComment()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'user_login_notification',
                'title' => __('User Login Alerts', 'wp-sms'),
                'subtitle' => __('Send an SMS to the Admin Mobile Number when users log in.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'notif_user_login',
                        'label' => __('Enable', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send an SMS when a user logs in.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_user_login_roles',
                        'label' => __('User Roles', 'wp-sms'),
                        'type' => 'multiselect',
                        'options' => $this->getRoles(),
                        'description' => __('Only alert for these roles.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'notif_user_login_template',
                        'label' => __('Message Template', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Write your SMS for login alerts. Use the variables below.', 'wp-sms') . '<br>' . NotificationFactory::getUser()->printVariables()
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
