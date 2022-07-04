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

        //Published New Posts Actions
        if (isset($this->options['notif_publish_new_post'])) {
            add_action('add_meta_boxes', array($this, 'notification_meta_box'));
            add_action("wp_insert_post", array($this, 'notify_subscribers_for_published_post'), 10, 3);
        }

        // Check the send to author of the post is enabled or not
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
            add_meta_box('subscribe-meta-box', __('SMS Notification', 'wp-sms'), array($this, 'notification_meta_box_handler'), $postType, 'normal', 'high');
        }
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
        $forceToSend      = isset($this->options['notif_publish_new_post_force']) ? true : false;
        $defaultGroup     = isset($this->options['notif_publish_new_post_default_group']) ? $this->options['notif_publish_new_post_default_group'] : false;
        include_once WP_SMS_DIR . "includes/templates/meta-box.php";
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
     * Send SMS when a new post add
     *
     * @param $postID
     * @param \WP_Post $post Post object.
     * @param $update
     *
     * @return null
     * @internal param $post_id
     */
    public function notify_subscribers_for_published_post($postID, $post, $update)
    {
        if ($post->post_status === 'publish') {
            // post types selection
            $specified_post_types = $this->extractPostTypeFromOption('notif_publish_new_post_type');

            if (in_array($post->post_type, $specified_post_types) == false) {
                return;
            }

            $isForce             = isset($this->options['notif_publish_new_post_force']) && $this->options['notif_publish_new_post_force'];
            $defaultGroup        = isset($this->options['notif_publish_new_post_default_group']) ? $this->options['notif_publish_new_post_default_group'] : '';
            $defaultReceiver     = isset($this->options['notif_publish_new_post_receiver']) ? $this->options['notif_publish_new_post_receiver'] : '';
            $defaultPostTemplate = isset($this->options['notif_publish_new_post_template']) ? $this->options['notif_publish_new_post_template'] : '';

            if (count($_POST) == 0 && $update == 1 && $postID) {
                return;
            }

            if (is_admin() && isset($_POST['post_ID'])) {
                $defaultReceiver     = isset($_REQUEST['wps_send_to']) ? $_REQUEST['wps_send_to'] : '';
                $isForce             = ($defaultReceiver == '0' ? false : true);
                $defaultGroup        = isset($_REQUEST['wps_subscribe_group']) ? sanitize_text_field($_REQUEST['wps_subscribe_group']) : '';
                $defaultPostTemplate = isset($_REQUEST['wpsms_text_template']) ? sanitize_text_field($_REQUEST['wpsms_text_template']) : '';
            }

            if ($isForce) {
                if ($defaultReceiver == 'subscriber') {
                    if ($defaultGroup == 'all') {
                        $this->sms->to = $this->db->get_col("SELECT mobile FROM {$this->tb_prefix}sms_subscribes");
                    } else {
                        $this->sms->to = $this->db->get_col("SELECT mobile FROM {$this->tb_prefix}sms_subscribes WHERE group_ID = '$defaultGroup'");
                    }
                } elseif ($defaultReceiver == 'numbers') {
                    $this->sms->to = explode(',', sanitize_text_field($_REQUEST['wps_mobile_numbers']));
                }

                $notif_publish_new_post_words_count = isset($this->options['notif_publish_new_post_words_count']) ? intval($this->options['notif_publish_new_post_words_count']) : false;
                $words_limit                        = ($notif_publish_new_post_words_count == NULL) ? 10 : $notif_publish_new_post_words_count;
                $template_vars                      = array(
                    '%post_title%'     => get_the_title($post->ID),
                    '%post_content%'   => wp_trim_words($post->post_content, $words_limit),
                    '%post_url%'       => wp_sms_shorturl(wp_get_shortlink($post->ID)),
                    '%post_date%'      => get_post_time('Y-m-d H:i:s', false, $post->ID, true),
                    '%post_thumbnail%' => get_the_post_thumbnail_url($post->ID),
                );

                $message = \WP_SMS\Helper::getOutputMessageVariables($template_vars, $defaultPostTemplate, array(
                    'method'  => 'notify_subscribers_for_published_post',
                    'post_id' => $postID,
                    'post'    => $post,
                ));

                /**
                 * Pass the thumbnail to media to send as MMS
                 */
                if (isset($this->options['notif_publish_new_send_mms']) and $this->options['notif_publish_new_send_mms']) {
                    $this->sms->media = [get_the_post_thumbnail_url($post->ID)];
                }

                $this->sms->msg = $message;
                $this->sms->SendSMS();
            }
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

        // get user mobile number
        $userMobileNumber = Helper::getUserMobileNumberByUserId($user_id);

        /**
         * Send SMS to user register.
         */
        if ($userMobileNumber or ($request and !is_array($request))) {

            if ($userMobileNumber) {
                $this->sms->to = array($userMobileNumber);
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
            '%comment_author_url%'   => wp_sms_shorturl($comment_object->comment_author_url),
            '%comment_author_IP%'    => $comment_object->comment_author_IP,
            '%comment_date%'         => $comment_object->comment_date,
            '%comment_content%'      => $comment_object->comment_content,
            '%comment_url%'          => wp_sms_shorturl(get_comment_link($comment_object)),
        );
        $message        = str_replace(array_keys($template_vars), array_values($template_vars), $this->options['notif_new_comment_template']);
        $this->sms->msg = $message;
        $this->sms->SendSMS();
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
            $this->sms->to = array($this->options['admin_mobile_number']);

            if (isset($this->options['notif_user_login_roles']) && $this->options['notif_user_login_roles']) {
                if (in_array($username->roles[0], $this->options['notif_user_login_roles']) == false) {
                    return;
                }
            }

            $template_vars = array(
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
            '%post_url%'     => wp_sms_shorturl(wp_get_shortlink($ID)),
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
    function notify_author_for_published_post($new_status, $old_status, $post)
    {
        if ('publish' === $new_status) {
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