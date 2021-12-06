<?php

namespace WP_SMS;

use WP_SMS\Admin\Helper;
use WP_SMS\Pro\Scheduled;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * Class Send SMS Page
 */
class SMS_Send
{
    public $sms;
    protected $db;
    protected $tb_prefix;
    protected $options;

    public function __construct()
    {
        global $wpdb, $sms;

        $this->db        = $wpdb;
        $this->tb_prefix = $wpdb->prefix;
        $this->sms       = $sms;
        $this->options   = Option::getOptions();
    }

    /**
     * Sending sms admin page
     *
     * @param Not param
     */
    public function render_page()
    {
        $get_group_result        = $this->db->get_results("SELECT * FROM `{$this->db->prefix}sms_subscribes_group`");
        $get_users_mobile        = $this->db->get_col("SELECT `meta_value` FROM `{$this->db->prefix}usermeta` WHERE `meta_key` = 'mobile' AND `meta_value` != '' ");
        $woocommerceCustomers    = [];
        $buddyPressMobileNumbers = [];
        $proIsActive             = Version::pro_is_active();

        if (class_exists('woocommerce') and class_exists('WP_SMS\Pro\WooCommerce\Helper')) {
            $woocommerceCustomers = \WP_SMS\Pro\WooCommerce\Helper::getCustomersNumbers();
        }

        if (class_exists('BuddyPress') and class_exists('WP_SMS\Pro\BuddyPress')) {
            $buddyPressMobileNumbers = \WP_SMS\Pro\BuddyPress::getTotalMobileNumbers();
        }

        //Get User Mobile List by Role
        $wpsms_list_of_role = array();
        foreach (wp_roles()->role_names as $key_item => $val_item) {
            $wpsms_list_of_role[$key_item] = array(
                "name"  => $val_item,
                "count" => Helper::getUsersList($key_item, true)
            );
        }

        $gateway_name   = Option::getOption('gateway_name');
        $credit         = get_option('wpsms_gateway_credit');
        $successMessage = __('The SMS sent successfully', 'wp-sms');

        if (isset($_POST['SendSMS'])) {
            if ($_POST['wp_get_message']) {
                if ($_POST['wp_send_to'] == "wp_subscribe_username") {
                    if ($_POST['wpsms_group_name'] == 'all') {
                        $this->sms->to = $this->db->get_col("SELECT mobile FROM {$this->db->prefix}sms_subscribes WHERE `status` = '1'");
                    } else {
                        $this->sms->to = $this->db->get_col("SELECT mobile FROM {$this->db->prefix}sms_subscribes WHERE `status` = '1' AND `group_ID` = '" . sanitize_text_field($_POST['wpsms_group_name']) . "'");
                    }
                } else if ($_POST['wp_send_to'] == "wp_users") {
                    $this->sms->to = $get_users_mobile;
                } else if ($_POST['wp_send_to'] == "wp_tellephone") {
                    $numbers = wp_unslash($_POST['wp_get_number']);
                    if (strpos($numbers, ',') !== false) {
                        $this->sms->to = explode(",", $numbers);
                    } else {
                        $this->sms->to = explode("\n", str_replace("\r", "", $numbers));
                    }
                } else if ($_POST['wp_send_to'] == "wp_role") {
                    $to   = array();
                    $list = Helper::getUsersList($_POST['wpsms_group_role']);
                    foreach ($list as $user) {
                        $to[] = $user->mobile;
                    }
                    $this->sms->to = $to;
                } else if ($_POST['wp_send_to'] == "wc_users") {
                    $this->sms->to = $woocommerceCustomers;
                } else if ($_POST['wp_send_to'] == "bp_users") {
                    $this->sms->to = $buddyPressMobileNumbers;
                }

                $this->sms->from = sanitize_text_field($_POST['wp_get_sender']);
                $this->sms->msg  = sanitize_text_field($_POST['wp_get_message']);

                /**
                 * Flash
                 */
                if (isset($_POST['wp_flash']) and $_POST['wp_flash'] == 'true') {
                    $this->sms->isflash = true;
                } else {
                    $this->sms->isflash = false;
                }

                /**
                 * Media
                 */
                if (isset($_POST['wpsms_mms_image'])) {
                    $mmsImages = wp_sms_sanitize_array($_POST['wpsms_mms_image']);

                    if ($this->sms->supportMedia and count(array_filter($mmsImages)) == count($mmsImages)) {
                        $this->sms->media = $mmsImages;
                    }
                }

                /**
                 * Scheduled
                 */
                if (isset($_POST['wpsms_scheduled']) and isset($_POST['schedule_status']) and $_POST['schedule_status'] and $_POST['wpsms_scheduled']) {
                    $wpsms_scheduled = sanitize_text_field($_POST['wpsms_scheduled']);
                    $response        = Scheduled::add($wpsms_scheduled, $this->sms->from, $this->sms->msg, $this->sms->to, true, $this->sms->media);
                    $successMessage  = __('SMS scheduled successfully!', 'wp-sms');
                } else {

                    // Send sms
                    if (empty($this->sms->to)) {
                        $response = new \WP_Error('error', __('The selected user list is empty, please select another valid users list from send to option.', 'wp-sms'));
                    } else {
                        $response = $this->sms->SendSMS();
                    }
                }

                if (is_wp_error($response)) {
                    if (is_array($response->get_error_message())) {
                        $response = print_r($response->get_error_message(), 1);
                    } else {
                        $response = $response->get_error_message();
                    }

                    echo "<div class='error'><p>" . sprintf(__('<strong>Error! Gateway response:</strong> %s', 'wp-sms'), $response) . "</p></div>";
                } else {

                    echo "<div class='updated'><p>{$successMessage}</p></div>";
                    $credit = Gateway::credit();
                }
            } else {
                echo "<div class='error'><p>" . __('Please enter your SMS message.', 'wp-sms') . "</p></div>";
            }
        }

        include_once WP_SMS_DIR . "includes/admin/send/send-sms.php";
    }
}
