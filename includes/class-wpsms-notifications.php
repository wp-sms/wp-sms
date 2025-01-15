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
        global $wpdb;

        $this->date      = WP_SMS_CURRENT_DATE;
        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;

        $this->addUserRegisterHook();
        $this->addNewCommentHook();
        $this->addUserLoginHook();
        $this->addPublishNewPostHooks();
        $this->addAuthorNotificationHook();
        $this->addCoreUpdateHook();
    }

    /**
     * Add hook for handling core update notifications.
     */
    private function addCoreUpdateHook()
    {
        if (Option::getOption('notif_publish_new_wpversion')) {
            add_action('init', [$this, 'handleCoreUpdateNotifications']);
        }
    }

    /**
     * Handle core update notifications.
     */
    public function handleCoreUpdateNotifications()
    {
        global $wp_version;

        $update = get_site_transient('update_core');
        $update = is_object($update) && isset($update->updates) ? $update->updates : [];

        if (isset($update[1]) && $update[1]->current > $wp_version) {
            if (!get_option('wp_last_send_notification')) {
                $receiver     = [Option::getOption('admin_mobile_number')];
                $message_body = sprintf(
                    esc_html__('WordPress %s is available! Please update now', 'wp-sms'),
                    $update[1]->current
                );

                $notification = NotificationFactory::getCustom();
                $notification->send($message_body, $receiver);

                update_option('wp_last_send_notification', true);
            }
        } else {
            update_option('wp_last_send_notification', false);
        }
    }

    /**
     * Add hook for user registration notifications.
     */
    private function addUserRegisterHook()
    {
        if (Option::getOption('notif_register_new_user')) {
            add_action('user_register', [$this, 'new_user'], 10, 1);
        }
    }

    /**
     * Add hook for new comment notifications.
     */
    private function addNewCommentHook()
    {
        if (Option::getOption('notif_new_comment')) {
            add_action('wp_insert_comment', [$this, 'new_comment'], 99, 2);
        }
    }

    /**
     * Add hook for user login notifications.
     */
    private function addUserLoginHook()
    {
        if (Option::getOption('notif_user_login')) {
            add_action('wp_login', [$this, 'login_user'], 99, 2);
        }
    }

    /**
     * Add hooks for publishing new posts.
     */
    private function addPublishNewPostHooks()
    {
        if (Option::getOption('notif_publish_new_post')) {
            add_action('add_meta_boxes', [$this, 'notification_meta_box']);
            add_action('wp_insert_post', [$this, 'notify_subscribers_for_published_post'], 10, 3);
        }
    }

    /**
     * Add hook for notifying post authors on publication.
     */
    private function addAuthorNotificationHook()
    {
        if (Option::getOption('notif_publish_new_post_author')) {
            add_action('transition_post_status', [$this, 'notify_author_for_published_post'], 10, 3);
        }
    }

    /**
     * Handle new user registration notifications.
     *
     * @param int $user_id User ID.
     */
    public function new_user($user_id)
    {
        $adminMobileNumber = Option::getOption('admin_mobile_number');

        // Notify admin about new user registration
        if ($adminMobileNumber) {
            $message  = Option::getOption('notif_register_new_user_admin_template');
            $receiver = apply_filters('wp_sms_admin_notify_registration', [$adminMobileNumber]);

            $notification = NotificationFactory::getUser($user_id);
            $notification->send($message, $receiver);
        }

        // Notify the user about registration
        $userMobileNumber = Helper::getUserMobileNumberByUserId($user_id);
        $receiver         = [];

        if ($userMobileNumber) {
            $receiver = [$userMobileNumber];
        } elseif (isset($_REQUEST['mobile'])) {
            $userMobileNumberFromRequest = sanitize_text_field($_REQUEST['mobile']);
            $receiver                    = [$userMobileNumberFromRequest];
        } elseif (isset($_REQUEST['phone_number'])) {
            // used for sending SMS to user after registration on LOGIN page.
            $userMobileNumberFromRequest = sanitize_text_field($_REQUEST['phone_number']);
            $receiver                    = [$userMobileNumberFromRequest];
        }

        if ($receiver) {
            $message      = Option::getOption('notif_register_new_user_template');
            $notification = NotificationFactory::getUser($user_id);
            $notification->send($message, $receiver);
        }
    }

    /**
     * Handle new comment notifications.
     *
     * @param int $comment_id Comment ID.
     * @param object $comment_object Comment object.
     */
    public function new_comment($comment_id, $comment_object)
    {
        // Skip notifications for specific comment types
        if (in_array($comment_object->comment_type, ['order_note', 'edd_payment_note'])) {
            return;
        }

        $message  = Option::getOption('notif_new_comment_template');
        $receiver = [Option::getOption('admin_mobile_number')];

        $notification = NotificationFactory::getComment($comment_id);
        $notification->send($message, $receiver);
    }

    /**
     * Handle user login notifications.
     *
     * @param string $username_login The username used to log in.
     * @param \WP_User $username The WP_User object.
     */
    public function login_user($username_login, $username)
    {
        $adminMobileNumber = Option::getOption('admin_mobile_number');

        if ($adminMobileNumber) {
            $allowedRoles = Option::getOption('notif_user_login_roles') ?: [];

            // Check if user role matches allowed roles
            if ($allowedRoles && !in_array($username->roles[0], $allowedRoles, true)) {
                return;
            }

            $message  = Option::getOption('notif_user_login_template');
            $receiver = [$adminMobileNumber];

            $notification = NotificationFactory::getUser($username->ID);
            $notification->send($message, $receiver);
        }
    }

    /**
     * Add subscribe meta box to the post
     */
    public function notification_meta_box()
    {
        foreach ($this->extractPostTypeFromOption('notif_publish_new_post_type') as $postType) {
            add_meta_box('subscribe-meta-box', esc_html__('SMS Notification', 'wp-sms'), [$this, 'notification_meta_box_handler'], $postType, 'normal', 'high');
        }
    }

    /**
     * New post manual send SMS
     *
     * @param WP_Post $post
     */
    public function notification_meta_box_handler($post)
    {
        $get_group_result = $this->db->get_results("SELECT * FROM {$this->db->prefix}sms_subscribes_group");
        $username_active  = $this->db->query("SELECT * FROM {$this->db->prefix}sms_subscribes WHERE status = '1'");
        $forceToSend      = Option::getOption('notif_publish_new_post_force');
        $defaultGroup     = Option::getOption('notif_publish_new_post_default_group');
        $selected_roles   = Option::getOption('notif_publish_new_post_users');

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
     * Send SMS notification to subscribers when a new post is published or scheduled.
     */
    public function notify_subscribers_for_published_post($postID, $post, $update)
    {
        if ($post->post_status !== 'publish' && $post->post_status !== 'future') {
            return;
        }

        $specified_post_types = $this->extractPostTypeFromOption('notif_publish_new_post_type');

        if (!in_array($post->post_type, $specified_post_types)) {
            return;
        }

        if (!isset($_REQUEST['wpsms_text_template']) || $_REQUEST['wps_send_to'] == '0') {
            return;
        }

        // Process recipients and send notifications
        $recipients = isset($_REQUEST['wps_send_to']) ? sanitize_text_field($_REQUEST['wps_send_to']) : '';
        $message    = sanitize_text_field($_REQUEST['wpsms_text_template']);
        $receiver   = [];

        switch ($recipients) {
            case 'subscriber':
                $group = isset($_REQUEST['wps_subscribe_group']) ? sanitize_text_field($_REQUEST['wps_subscribe_group']) : 'all';
                if ($group === 'all') {
                    $receiver = Newsletter::getSubscribers(null, true);
                } else {
                    $receiver = Newsletter::getSubscribers([$group], true);
                }
                break;
            case 'numbers':
                $raw_numbers = isset($_REQUEST['wps_mobile_numbers']) ? sanitize_text_field($_REQUEST['wps_mobile_numbers']) : '';
                $receiver = explode(',', $raw_numbers);
                break;
            case 'users':
                $receiver = Helper::getUsersMobileNumbers(Option::getOption('notif_publish_new_post_users'));
                break;
        }

        if (!empty($receiver) && $message) {
            $notification = NotificationFactory::getPost($postID);
            $notification->send($message, $receiver);
        }
    }

    /**
     * Notify authors of published posts.
     */
    public function notify_author_for_published_post($new_status, $old_status, $post)
    {
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $post_types_option = $this->extractPostTypeFromOption('notif_publish_new_post_author_post_type');

            if (in_array($post->post_type, $post_types_option)) {
                $this->new_post_published($post->ID, $post);
            }
        }
    }

    /**
     * Notify authors of newly published posts.
     */
    public function new_post_published($ID, $post)
    {
        $message  = Option::getOption('notif_publish_new_post_author_template');
        $receiver = [get_user_meta($post->post_author, 'mobile', true)];

        $notification = NotificationFactory::getPost($ID);
        $notification->send($message, $receiver);
    }

    /**
     * Extract post types from an option.
     *
     * @param string $optionName
     * @return array
     */
    private function extractPostTypeFromOption($optionName)
    {
        $specified_post_types = Option::getOption($optionName) ?: [];

        foreach ($specified_post_types as $key => $post_type) {
            $value                      = explode('|', $post_type)[1];
            $specified_post_types[$key] = $value;
        }

        return $specified_post_types;
    }
}

new Notifications();
