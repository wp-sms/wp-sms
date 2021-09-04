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
        $html       = '<form action="" method="post">
					    <table>
					        <tr>
					            <td style="padding-top: 10px;">
					                <label for="wp_group_name"
					                       class="wp_sms_subscribers_label">' . __('Name', 'wp-sms') . '</label>
					                <input type="text" id="wp_group_name" name="wp_group_name" value="' . esc_attr($group_name) . '"
					                       class="wp_sms_subscribers_input_text"/>
					                <input type="hidden" id="wp_group_name" name="group_id" value="' . $group_id . '"
							class="wp_sms_subscribers_input_text"/>
							</td>
							</tr>
							
							<tr>
							    <td colspan="2" style="padding-top: 20px;">
							        <input type="submit" class="button-primary" name="wp_update_group"
							               value="' . __('Edit', 'wp-sms') . '"/>
							    </td>
							</tr>
							</table>
						</form>';
        echo $html;
        wp_die(); // this is required to terminate immediately and return a proper response
    }

}

new Subscribers_Groups_Table_Edit();
