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

        // Add subscriber
        if (isset($_POST['wp_add_subscribe'])) {
            $group = isset($_POST['wpsms_group_name']) ? $_POST['wpsms_group_name'] : '';
            if ($group) {
                $result = Newsletter::addSubscriber($_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $group);
            } else {
                $result = Newsletter::addSubscriber($_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile']);
            }

            echo Helper::notice($result['message'], $result['result']);
        }

        // Edit subscriber page
        if (isset($_POST['wp_update_subscribe'])) {
            $group  = isset($_POST['wpsms_group_name']) ? $_POST['wpsms_group_name'] : '';
            $result = Newsletter::updateSubscriber($_POST['ID'], $_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $group, $_POST['wpsms_subscribe_status']);
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