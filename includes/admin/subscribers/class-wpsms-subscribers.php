<?php

namespace WP_SMS;

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
            // Verify nonce
            if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'wp_sms_subscriber_action')) {
                $groupIds            = isset($_POST['wpsms_group_name']) ? wp_sms_sanitize_array($_POST['wpsms_group_name']) : '';
                $wp_subscribe_name   = isset($_POST['wp_subscribe_name']) ? sanitize_text_field($_POST['wp_subscribe_name']) : '';
                $wp_subscribe_mobile = isset($_POST['wp_subscribe_mobile']) ? sanitize_text_field($_POST['wp_subscribe_mobile']) : '';
                $subscribe_status    = isset($_POST['wpsms_subscribe_status']) ? sanitize_text_field($_POST['wpsms_subscribe_status']) : '';
                if ($groupIds) {
                    foreach ($groupIds as $groupId) {
                        $result = Newsletter::addSubscriber($wp_subscribe_name, $wp_subscribe_mobile, $groupId, $subscribe_status);
                    }

                } else {
                    $result = Newsletter::addSubscriber($wp_subscribe_name, $wp_subscribe_mobile, '', $subscribe_status);
                }

                // Print notice
                Helper::notice($result['message'], $result['result']);
            } else {
                // Nonce verification failed
                Helper::notice(esc_html__('Access denied.', 'wp-sms'), false);
            }
        }

        // Edit subscriber page
        if (isset($_POST['wp_update_subscribe'])) {
            // Verify nonce
            if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'wp_sms_subscriber_action')) {
                $group               = isset($_POST['wpsms_group_name']) ? sanitize_text_field($_POST['wpsms_group_name']) : '';
                $ID                  = isset($_POST['ID']) ? sanitize_text_field($_POST['ID']) : '';
                $wp_subscribe_name   = isset($_POST['wp_subscribe_name']) ? sanitize_text_field($_POST['wp_subscribe_name']) : '';
                $wp_subscribe_mobile = isset($_POST['wp_subscribe_mobile']) ? sanitize_text_field($_POST['wp_subscribe_mobile']) : '';
                $subscribe_status    = isset($_POST['wpsms_subscribe_status']) ? sanitize_text_field($_POST['wpsms_subscribe_status']) : '';
                $result              = Newsletter::updateSubscriber($ID, $wp_subscribe_name, $wp_subscribe_mobile, $group, $subscribe_status);

                // Print notice
                Helper::notice($result['message'], $result['result']);
            } else {
                // Nonce verification failed
                Helper::notice(esc_html__('Access denied.', 'wp-sms'), false);
            }
        }

        include_once WP_SMS_DIR . 'includes/admin/subscribers/class-wpsms-subscribers-table.php';

        //Create an instance of our package class...
        $list_table = new Subscribers_List_Table();

        //Fetch, prepare, sort, and filter our data...
        $list_table->prepare_items();

        $args = [
            'list_table' => $list_table,
        ];

        echo Helper::loadTemplate('admin/subscribers.php', $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
