<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Notifications
{

    public $sms;
    public $date;
    public $options;

    /**
     * Wordpress Database
     *
     * @var string
     */
    protected $db;

    /**
     * Wordpress Table prefix
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

        if (isset($this->options['notif_publish_new_post'])) {
            add_action('add_meta_boxes', array($this, 'notification_meta_box'));
            add_action('publish_post', array($this, 'new_post'), 10, 2);
        }

        // Wordpress new version
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
                        $this->sms->to  = array($this->options['admin_mobile_number']);
                        $this->sms->msg = sprintf(__('WordPress %s is available! Please update now', 'wp-sms'), $update[1]->current);
                        $this->sms->SendSMS();

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

        // Check the send to author of the post is enabled or not
        if (Option::getOption('notif_publish_new_post_author')) {
            // Add transition publish post
            add_action('transition_post_status', array($this, 'transition_publish'), 10, 3);
        }
    }

    /**
     * Add subscribe meta box to the post
     */
    public function notification_meta_box()
    {
        add_meta_box('subscribe-meta-box', __('SMS', 'wp-sms'), array(
            $this,
            'notification_meta_box_handler'
        ), get_post_types(['public' => true]), 'normal', 'high');
    }

    /**
     * New post manual send SMS
     *
     * @param $post
     */
    public function notification_meta_box_handler($post)
    {
        global $wpdb;

        $get_group_result = $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}sms_subscribes_group`");
        $username_active  = $wpdb->query("SELECT * FROM {$wpdb->prefix}sms_subscribes WHERE status = '1'");
        include_once WP_SMS_DIR . "includes/templates/meta-box.php";
    }

    /**
     * Send SMS when a new post add
     *
     * @param $ID
     * @param $post
     *
     * @return null
     * @internal param $post_id
     */
    public function new_post($ID, $post)
    {
        if ($_REQUEST['wps_send_subscribe'] == 'yes') {
            if ($_REQUEST['wps_subscribe_group'] == 'all') {
                $this->sms->to = $this->db->get_col("SELECT mobile FROM {$this->tb_prefix}sms_subscribes");
            } else {
                $this->sms->to = $this->db->get_col("SELECT mobile FROM {$this->tb_prefix}sms_subscribes WHERE group_ID = '{$_REQUEST['wps_subscribe_group']}'");
            }

            $notif_publish_new_post_words_count = isset($this->options['notif_publish_new_post_words_count']) ? intval($this->options['notif_publish_new_post_words_count']) : false;
            $words_limit                        = ($notif_publish_new_post_words_count === false) ? 10 : $notif_publish_new_post_words_count;
            $template_vars                      = array(
                '%post_title%'   => get_the_title($ID),
                '%post_content%' => wp_trim_words($post->post_content, $words_limit),
                '%post_url%'     => wp_get_shortlink($ID),
                '%post_date%'    => get_post_time('Y-m-d H:i:s', false, $ID, true),
            );

            $message = str_replace(array_keys($template_vars), array_values($template_vars), $_REQUEST['wpsms_text_template']);

            $this->sms->msg = $message;
            $this->sms->SendSMS();
        }
    }

    /**
     * Send SMS when a new user registered
     *
     * @param $user_id
     */
    public function new_user($user_id)
    {
        $user          = get_userdata($user_id);
        $template_vars = array(
            '%user_login%'    => $user->user_login,
            '%user_email%'    => $user->user_email,
            '%date_register%' => $this->date,
        );

        /**
         * Send SMS to admin
         */
        if (Option::getOption('admin_mobile_number')) {
            $this->sms->to  = apply_filters('wp_sms_admin_notify_registration', array($this->options['admin_mobile_number']));
            $message        = str_replace(array_keys($template_vars), array_values($template_vars), $this->options['notif_register_new_user_admin_template']);
            $this->sms->msg = $message;
            $this->sms->SendSMS();
        }

        // Modify request value.
        $request = apply_filters('wp_sms_user_notify_registration', $_REQUEST);

        /**
         * Send SMS to user register.
         */
        if (isset($user->mobile) or $request and !is_array($request)) {

            if (isset($user->mobile)) {
                $this->sms->to = array($user->mobile);
            } else if ($request) {
                $this->sms->to = array($request);
            }

            $message        = str_replace(array_keys($template_vars), array_values($template_vars), $this->options['notif_register_new_user_template']);
            $this->sms->msg = $message;
            $this->sms->SendSMS();
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

        $this->sms->to  = array($this->options['admin_mobile_number']);
        $template_vars  = array(
            '%comment_author%'       => $comment_object->comment_author,
            '%comment_author_email%' => $comment_object->comment_author_email,
            '%comment_author_url%'   => $comment_object->comment_author_url,
            '%comment_author_IP%'    => $comment_object->comment_author_IP,
            '%comment_date%'         => $comment_object->comment_date,
            '%comment_content%'      => $comment_object->comment_content,
            '%comment_url%'          => get_comment_link($comment_object),
        );
        $message        = str_replace(array_keys($template_vars), array_values($template_vars), $this->options['notif_new_comment_template']);
        $this->sms->msg = $message;
        $this->sms->SendSMS();
    }

    /**
     * Send SMS when user logged in
     *
     * @param $username_login
     * @param $username
     */
    public function login_user($username_login, $username)
    {

        if (Option::getOption('admin_mobile_number')) {
            $this->sms->to = array($this->options['admin_mobile_number']);

            $template_vars  = array(
                '%username_login%' => $username->user_login,
                '%display_name%'   => $username->display_name
            );
            $message        = str_replace(array_keys($template_vars), array_values($template_vars), $this->options['notif_user_login_template']);
            $this->sms->msg = $message;
            $this->sms->SendSMS();
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
        $message       = '';
        $template_vars = array(
            '%post_title%'   => get_the_title($ID),
            '%post_content%' => wp_trim_words($post->post_content, 10),
            '%post_url%'     => wp_get_shortlink($ID),
            '%post_date%'    => get_post_time('Y-m-d H:i:s', false, $ID, true),
        );
        $template      = isset($this->options['notif_publish_new_post_author_template']) ? $this->options['notif_publish_new_post_author_template'] : '';
        if ($template) {
            $message = str_replace(array_keys($template_vars), array_values($template_vars), $template);
        }
        $this->sms->to  = array(get_user_meta($post->post_author, 'mobile', true));
        $this->sms->msg = $message;
        $this->sms->SendSMS();
    }

    /**
     * Add only on publish transition actions
     *
     * @param $new_status
     * @param $old_status
     * @param $post
     */
    function transition_publish($new_status, $old_status, $post)
    {
        if ('publish' === $new_status && 'publish' !== $old_status) {
            $post_types_option = Option::getOption('notif_publish_new_post_author_post_type');

            // Check selected post types or not?
            if ($post_types_option and is_array($post_types_option)) {
                // Initialize values
                $post_types = array();
                foreach ($post_types_option as $post_publish_type) {
                    $value                 = explode('|', $post_publish_type);
                    $post_types[$value[1]] = $value[0];
                }
                if (array_key_exists($post->post_type, $post_types) and !user_can($post->post_author, $post_types[$post->post_type])) {
                    $this->new_post_published($post->ID, $post);
                }
            }
        }
    }

}

new Notifications();