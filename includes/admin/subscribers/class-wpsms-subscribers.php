<?php

namespace WP_SMS;

use WP_SMS\Admin\Helper;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Subscribers
{

    /**
     * Subscribe admin page
     */
    public function render_page()
    {

        add_thickbox();

        // Add subscriber
        if (isset($_POST['wp_add_subscribe'])) {
            $group               = isset($_POST['wpsms_group_name']) ? sanitize_text_field($_POST['wpsms_group_name']) : '';
            $wp_subscribe_name   = isset($_POST['wp_subscribe_name']) ? sanitize_text_field($_POST['wp_subscribe_name']) : '';
            $wp_subscribe_mobile = isset($_POST['wp_subscribe_mobile']) ? sanitize_text_field($_POST['wp_subscribe_mobile']) : '';

            if ($group) {
                $result = Newsletter::addSubscriber($wp_subscribe_name, $wp_subscribe_mobile, $group);
            } else {
                $result = Newsletter::addSubscriber($wp_subscribe_name, $wp_subscribe_mobile);
            }

            // Print notice
            echo Helper::notice($result['message'], $result['result']);
        }

        // Edit subscriber page
        if (isset($_POST['wp_update_subscribe'])) {
            $group               = isset($_POST['wpsms_group_name']) ? sanitize_text_field($_POST['wpsms_group_name']) : '';
            $ID                  = isset($_POST['ID']) ? sanitize_text_field($_POST['ID']) : '';
            $wp_subscribe_name   = isset($_POST['wp_subscribe_name']) ? sanitize_text_field($_POST['wp_subscribe_name']) : '';
            $wp_subscribe_mobile = isset($_POST['wp_subscribe_mobile']) ? sanitize_text_field($_POST['wp_subscribe_mobile']) : '';
            $subscribe_status    = isset($_POST['wpsms_subscribe_status']) ? sanitize_text_field($_POST['wpsms_subscribe_status']) : '';
            $result              = Newsletter::updateSubscriber($ID, $wp_subscribe_name, $wp_subscribe_mobile, $group, $subscribe_status);

            // Print notice
            echo Helper::notice($result['message'], $result['result']);
        }

        // Import subscriber page
        if (isset($_POST['wps_import'])) {
            include_once WP_SMS_DIR . "includes/admin/import.php";
        }

        include_once WP_SMS_DIR . 'includes/admin/subscribers/class-wpsms-subscribers-table.php';

        //Create an instance of our package class...
        $list_table = new Subscribers_List_Table();

        //Fetch, prepare, sort, and filter our data...
        $list_table->prepare_items();

        include_once WP_SMS_DIR . "includes/admin/subscribers/subscribers.php";
    }
}
