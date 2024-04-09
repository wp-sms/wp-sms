<?php

namespace WP_SMS;

use WP_Post;
use WP_SMS\Notification\NotificationFactory;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Notifications
{

    public $sms;
    public $date;
    public $options;

    /**
     * WordPress Database
     *
     * @var $wpdb
     */
    protected $db;

    /**
     * WordPress Table prefix
     *
     * @var string
     */
    protected $tb_prefix;

    /**
     * WP_SMS_Notifications constructor.
     */
    public function __construct()
    {
        global $sms, $wp_version, $wpdb;

        $this->sms       = $sms;
        $this->date      = WP_SMS_CURRENT_DATE;
        $this->options   = Option::getOptions();
        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;

        // WordPress new version
        if (isset($this->options['notif_publish_new_wpversion'])) {
            $update = get_site_transient('update_core');

            if (is_object($update) and isset($update->updates)) {
                $update = $update->updates;
            } else {
                $update = array();
            }

            if (isset($update[1])) {
                if ($update[1]->current > $wp_version and $this->sms->GetCredit()) {
                    if (get_option('wp_last_send_notification') == false) {

                        $receiver     = array($this->options['admin_mobile_number']);
                        // translators: %s: WordPress version
                        $message_body = sprintf(esc_html__('WordPress %s is available! Please update now', 'wp-sms'), $update[1]->current);
                        $notification = NotificationFactory::getCustom();
                        $notification->send($message_body, $receiver);

                        update_option('wp_last_send_notification', true);
                    }
                } else {
                    update_option('wp_last_send_notification', false);
                }
            }

        }

        if (isset($this->options['notif_register_new_user'])) {
            add_action('user_register', array($this, 'new_user'), 10, 1);
        }

        if (isset($this->options['notif_new_comment'])) {
            add_action('wp_insert_comment', array($this, 'new_comment'), 99, 2);
        }

        if (isset($this->options['notif_user_login'])) {
            add_action('wp_login', array($this, 'login_user'), 99, 2);
        }

        //Published New Posts Actions
        if (isset($this->options['notif_publish_new_post'])) {
            add_action('add_meta_boxes', array($this, 'notification_meta_box'));
            add_action("wp_insert_post", array($this, 'notify_subscribers_for_published_post'), 10, 3);
        }

        // Check sending to author of the post is enabled or not
        if (Option::getOption('notif_publish_new_post_author')) {
            // Add transition publish post
            add_action('transition_post_status', array($this, 'notify_author_for_published_post'), 10, 3);
        }

    }

    /**
     * Add subscribe meta box to the post
     */
    public function notification_meta_box()
    {
        foreach ($this->extractPostTypeFromOption('notif_publish_new_post_type') as $postType) {
            add_meta_box('subscribe-meta-box', esc_html__('SMS Notification', 'wp-sms'), array($this, 'notification_meta_box_handler'), $postType, 'normal', 'high');
        }
    }

    /**
     * New post manual send SMS
     *
     * @param $post
     */
    public function notification_meta_box_handler($post)
    {
        $get_group_result = $this->db->get_results("SELECT * FROM {$this->db->prefix}sms_subscribes_group");
        $username_active  = $this->db->query("SELECT * FROM {$this->db->prefix}sms_subscribes WHERE status = '1'");
        $forceToSend      = isset($this->options['notif_publish_new_post_force']);
        $defaultGroup     = isset($this->options['notif_publish_new_post_default_group']) ? $this->options['notif_publish_new_post_default_group'] : false;
        $selected_roles   = isset($this->options['notif_publish_new_post_users']) ? $this->options['notif_publish_new_post_users'] : false;

        $args = [
            'get_group_result'   => $get_group_result,
            'selected_roles'     => $selected_roles,
            'username_active'    => $username_active,
            'forceToSend'        => $forceToSend,
            'defaultGroup'       => $defaultGroup,
            'wpsms_list_of_role' => Helper::getListOfRoles(),
            'get_users_mobile'   => Helper::getUsersMobileNumbers(),
        ];

        echo Helper::loadTemplate('meta-box.php', $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * @param $optionName
     * @return array|mixed
     */
    private function extractPostTypeFromOption($optionName)
    {
        $specified_post_types = isset($this->options[$optionName]) ? $this->options[$optionName] : [];

        foreach ($specified_post_types as $key => $post_type) {
            $value                      = explode('|', $post_type)[1];
            $specified_post_types[$key] = $value;
        }

        return $specified_post_types;
    }

    /**
     * Send SMS notification to subscribers when a new post is published or scheduled.
     *
     * @param int $postID The ID of the post.
     * @param WP_Post $post The post object.
     * @param bool $update Whether this is an update to an existing post.
     *
     * @return void
     */
    public function notify_subscribers_for_published_post($postID, $post, $update)
    {
        // Check if the post is being published or scheduled
        if ($post->post_status === 'publish' || $post->post_status === 'future') {

            // Validate the post type and break the process there is no match for the selected post types
            $specified_post_types = $this->extractPostTypeFromOption('notif_publish_new_post_type');

            if (!in_array($post->post_type, $specified_post_types)) {
                return;
            }

            if (!isset($_REQUEST['wpsms_text_template']) || $_REQUEST['wps_send_to'] == '0') {
                return;
            }

            $termIds         = [];
            $specified_terms = isset($this->options['notif_publish_new_taxonomy_and_term']) ? $this->options['notif_publish_new_taxonomy_and_term'] : [];

            /**
             * Check terms
             */
            if ($specified_terms) {
                $taxonomies = get_object_taxonomies($post->post_type);
                $matchFound = false;

                foreach ($taxonomies as $taxonomy) {
                    $terms = get_the_terms($post, $taxonomy);

                    if (isset($terms)) {
                        foreach ($terms as $term) {
                            $termIds[] = $term->term_id;
                        }
                    }
                }

                foreach ($termIds as $item) {
                    if (in_array($item, $specified_terms)) {
                        $matchFound = true;
                    }
                }

                if (!$matchFound) {
                    return;
                }
            }

            // Save notification data in post meta if in the admin area and a post ID exists
            if (is_admin() && $postID) {

                if (isset($_REQUEST['wps_send_to'])) {
                    update_post_meta($postID, 'wp_sms_receiver', sanitize_text_field($_REQUEST['wps_send_to']));
                } else {
                    // Break the process if there is no recipient for the SMS
                    return;
                }

                if (isset($this->options['notif_publish_new_post_force'])) {
                    update_post_meta($postID, 'wp_sms_force_sms', true);
                }

                if (isset($_REQUEST['wps_subscribe_group'])) {
                    update_post_meta($postID, 'wp_sms_groups', sanitize_text_field($_REQUEST['wps_subscribe_group']));
                }

                if (isset($_REQUEST['wps_mobile_numbers'])) {
                    update_post_meta($postID, 'wp_sms_numbers', sanitize_text_field($_REQUEST['wps_mobile_numbers']));
                }

                if (isset($_REQUEST['wpsms_roles'])) {
                    update_post_meta($postID, 'wp_sms_roles', wp_sms_sanitize_array($_REQUEST['wpsms_roles']));
                }

                if (isset($_REQUEST['wpsms_text_template'])) {
                    update_post_meta($postID, 'wp_sms_message_body', sanitize_text_field($_REQUEST['wpsms_text_template']));
                }

            }

            // Return if the post is scheduled to be published in the future
            if ($post->post_status === 'future') {
                return;
            }

            // Retrieve data from post meta
            $recipients        = get_post_meta($postID, 'wp_sms_receiver', true);
            $subscriber_groups = get_post_meta($postID, 'wp_sms_groups', true);
            $numbers           = get_post_meta($postID, 'wp_sms_numbers', true);
            $user_roles        = get_post_meta($postID, 'wp_sms_roles', true);
            $message_body      = get_post_meta($postID, 'wp_sms_message_body', true);
            $receiver          = [];
            $mediaUrls         = [];

            // Retrieve recipient mobile numbers
            // $recipients can be 'subscriber', 'numbers', or 'users'
            switch ($recipients) {

                // If $subscriber_groups is 'all', get mobile numbers for all subscribers
                // Otherwise, get mobile numbers for subscribers in the specified group
                case 'subscriber':
                    if ($subscriber_groups == 'all') {
                        $receiver = Newsletter::getSubscribers();
                    } else {
                        $receiver = Newsletter::getSubscribers(array($subscriber_groups));
                    }
                    break;

                // Get mobile numbers from the comma-separated string in $numbers
                case 'numbers':
                    $receiver = explode(',', $numbers);
                    break;

                // Get mobile numbers for users with the specified roles
                case 'users':
                    $receiver = Helper::getUsersMobileNumbers($user_roles);
                    break;
            }

            // If the "notif_publish_new_send_mms" option is set and enabled, send the message as an MMS with the post
            if (isset($this->options['notif_publish_new_send_mms']) and $this->options['notif_publish_new_send_mms']) {
                $mediaUrls = [get_the_post_thumbnail_url($post->ID)];
            }

            if (empty($receiver) || !$message_body) {
                return;
            }

            // Fire notification
            $notification = NotificationFactory::getPost($postID);
            $notification->send($message_body, $receiver, $mediaUrls);
        }
    }

    /**
     * Send SMS when a new user registered
     *
     * @param $user_id
     */
    public function new_user($user_id)
    {
        $adminMobileNumber = Option::getOption('admin_mobile_number');

        /**
         * Send SMS to admin
         */
        if ($adminMobileNumber) {
            $message  = Option::getOption('notif_register_new_user_admin_template');
            $receiver = apply_filters('wp_sms_admin_notify_registration', array($adminMobileNumber));

            // Fire notification
            $notification = NotificationFactory::getUser($user_id);
            $notification->send($message, $receiver);
        }

        $userMobileNumber = Helper::getUserMobileNumberByUserId($user_id);
        $receiver         = [];

        /**
         * Send SMS to user
         */
        if ($userMobileNumber) {
            $receiver = array($userMobileNumber);
        } else if (isset($_REQUEST['mobile'])) {
            $userMobileNumberFromRequest = apply_filters('wp_sms_user_notify_registration', sanitize_text_field($_REQUEST['mobile']));
            $receiver                    = array($userMobileNumberFromRequest);
        }

        // Fire notification
        if ($receiver) {
            $message      = Option::getOption('notif_register_new_user_template');
            $notification = NotificationFactory::getUser($user_id);
            $notification->send($message, $receiver);
        }
    }

    /**
     * Send SMS when new comment add
     *
     * @param $comment_id
     * @param $comment_object
     */
    public function new_comment($comment_id, $comment_object)
    {

        if ($comment_object->comment_type == 'order_note') {
            return;
        }

        if ($comment_object->comment_type == 'edd_payment_note') {
            return;
        }

        $message  = Option::getOption('notif_new_comment_template');
        $receiver = array(Option::getOption('admin_mobile_number'));

        // Fire notification
        $notification = NotificationFactory::getComment($comment_id);
        $notification->send($message, $receiver);
    }

    /**
     * Send SMS when user logged in
     *
     * @param $username_login
     * @param \WP_User $username
     */
    public function login_user($username_login, $username)
    {
        if (Option::getOption('admin_mobile_number')) {
            if (isset($this->options['notif_user_login_roles']) && $this->options['notif_user_login_roles']) {
                if (in_array($username->roles[0], $this->options['notif_user_login_roles']) == false) {
                    return;
                }
            }

            $message  = Option::getOption('notif_user_login_template');
            $receiver = array(Option::getOption('admin_mobile_number'));

            // Fire notification
            $notification = NotificationFactory::getUser($username->ID);
            $notification->send($message, $receiver);
        }
    }

    /**
     * Send sms to author of the post if published
     *
     * @param $ID
     * @param $post
     */
    public function new_post_published($ID, \WP_Post $post)
    {
        $message  = wp_sms_get_option('notif_publish_new_post_author_template');
        $receiver = array(get_user_meta($post->post_author, 'mobile', true));

        // Fire notification
        $notification = NotificationFactory::getPost($post->ID);
        $notification->send($message, $receiver);
    }

    /**
     * Add only on publish transition actions
     *
     * @param $new_status
     * @param $old_status
     * @param $post
     */
    public function notify_author_for_published_post($new_status, $old_status, $post)
    {
        if ('publish' === $new_status && 'publish' !== $old_status) {
            $post_types_option = $this->extractPostTypeFromOption('notif_publish_new_post_author_post_type');

            // Check selected post types or not?
            if ($post_types_option) {
                if (in_array($post->post_type, $post_types_option)) {
                    $this->new_post_published($post->ID, $post);
                }
            }
        }
    }

}

new Notifications();