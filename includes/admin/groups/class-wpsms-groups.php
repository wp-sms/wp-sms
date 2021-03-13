<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly


use WP_SMS\Admin\Helper;

class Groups
{

    /**
     * Subscribe groups admin page
     */
    public function render_page()
    {

        //Add groups
        if (isset($_POST['wp_add_group'])) {
            $result = Newsletter::addGroup($_POST['wp_group_name']);
            echo Helper::notice($result['message'], $result['result']);
        }

        // Manage groups
        if (isset($_POST['wp_update_group'])) {
            $result = Newsletter::updateGroup($_POST['group_id'], $_POST['wp_group_name']);
            echo Helper::notice($result['message'], $result['result']);
        }

        include_once WP_SMS_DIR . '/includes/admin/groups/class-wpsms-groups-table.php';

        //Create an instance of our package class...
        $list_table = new Subscribers_Groups_List_Table();

        //Fetch, prepare, sort, and filter our data...
        $list_table->prepare_items();

        include_once WP_SMS_DIR . "includes/admin/groups/groups.php";
    }
}