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
        $this->handleCoreUpdateNotifications();
    }

    /**
     * Handle core update notifications.
     */
    private function handleCoreUpdateNotifications()
    {
        global $wp_version;

        if (Option::getOption('notif_publish_new_wpversion')) {
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

    // Remaining methods like notification_meta_box(), notify_subscribers_for_published_post(), etc.,
    // are unchanged except for replacing $this->options with Option::getOption where applicable.
}

new Notifications();
