<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

//Edit Groups Class
class Subscribers_Groups_Table_Edit
{

    public $db;
    protected $tb_prefix;

    public function __construct()
    {
        global $wpdb;

        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;

        add_action('wp_ajax_wp_sms_edit_group', array($this, 'wp_sms_edit_group'));
    }

    function wp_sms_edit_group()
    {
        //set Actiom Values
        $group_id   = isset($_GET['group_id']) ? sanitize_text_field($_GET['group_id']) : null;
        $group_name = isset($_GET['group_name']) ? sanitize_text_field($_GET['group_name']) : null;

        echo Helper::loadTemplate('/admin/group-form.php', array(
            'group_id'   => $group_id,
            'group_name' => $group_name
        ));
        exit;
    }
}

new Subscribers_Groups_Table_Edit();
