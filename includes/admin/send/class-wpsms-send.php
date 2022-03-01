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

        include_once WP_SMS_DIR . "includes/admin/send/send-sms.php";
    }
}
