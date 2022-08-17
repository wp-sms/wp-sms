<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly


class Subscribers_Subscribers_Table_Edit
{

    public $db;
    protected $tb_prefix;

    public function __construct()
    {
        global $wpdb;

        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;

        add_action('wp_ajax_wp_sms_edit_subscriber', array($this, 'wp_sms_edit_subscriber'));
    }

    function wp_sms_edit_subscriber()
    {
        //set Action Values
        $subscriber_id = isset($_GET['subscriber_id']) ? sanitize_text_field($_GET['subscriber_id']) : null;

        //Load subscriber
        $subscriber = Newsletter::getSubscriber($subscriber_id);
        $groups     = Newsletter::getGroups();

        echo Helper::loadTemplate('admin/subscriber-edit-form.php', array(
            'subscriber_id' => $subscriber_id,
            'subscriber'    => $subscriber,
            'groups'        => $groups
        ));
        exit;
    }

}

new Subscribers_Subscribers_Table_Edit();
