<?php

namespace WP_SMS;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

class Groups
{

    /**
     * Subscribe groups admin page
     */
    public function render_page()
    {
        // Add groups
        if (isset($_POST['wp_add_group'])) {
            // Verify nonce
            if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'wp_sms_group_action')) {
                $group_name = sanitize_text_field($_POST['wp_group_name']);
                $result     = Newsletter::addGroup($group_name);
                Helper::notice($result['message'], $result['result']);
            } else {
                // Nonce verification failed
                Helper::notice(esc_html__('Access denied.', 'wp-sms'), false);
            }
        }

        // Manage groups
        if (isset($_POST['wp_update_group'])) {
            // Verify nonce
            if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'wp_sms_group_action')) {
                $group_id   = sanitize_text_field($_POST['group_id']);
                $group_name = sanitize_text_field($_POST['wp_group_name']);
                $result     = Newsletter::updateGroup($group_id, $group_name);
                Helper::notice($result['message'], $result['result']);
            } else {
                // Nonce verification failed
                Helper::notice(esc_html__('Access denied.', 'wp-sms'), false);
            }
        }

        include_once WP_SMS_DIR . '/includes/admin/groups/class-wpsms-groups-table.php';

        //Create an instance of our package class...
        $list_table = new Subscribers_Groups_List_Table();

        //Fetch, prepare, sort, and filter our data...
        $list_table->prepare_items();

        $args = [
            'list_table' => $list_table,
        ];

        echo \WP_SMS\Helper::loadTemplate('admin/groups.php', $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}