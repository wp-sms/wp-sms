<?php

namespace WP_SMS;

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
        $woocommerceCustomers = [];
        if (class_exists('woocommerce') and class_exists('WP_SMS\Pro\WooCommerce\Helper')) {
            $woocommerceCustomers = \WP_SMS\Pro\WooCommerce\Helper::getCustomersNumbers();
        }

        $buddyPressMobileNumbers = [];
        if (class_exists('BuddyPress') and class_exists('WP_SMS\Pro\BuddyPress')) {
            $buddyPressMobileNumbers = \WP_SMS\Pro\BuddyPress::getTotalMobileNumbers();
        }

        //Get User Mobile List by Role
        $wpsms_list_of_role = array();
        foreach (wp_roles()->role_names as $key_item => $val_item) {
            $wpsms_list_of_role[$key_item] = array(
                "name"  => $val_item,
                "count" => count(Helper::getUsersMobileNumbers($key_item))
            );
        }

        echo Helper::loadTemplate('admin/send-sms.php', [
            'get_group_result'        => Newsletter::getGroups(),
            'get_users_mobile'        => Helper::getUsersMobileNumbers(),
            'proIsActive'             => Version::pro_is_active(),
            'woocommerceCustomers'    => $woocommerceCustomers,
            'buddyPressMobileNumbers' => $buddyPressMobileNumbers,
            'wpsms_list_of_role'      => $wpsms_list_of_role,
            'smsObject'               => $this->sms,
            'gatewayCredit'           => $this->sms::credit(),
        ]);
    }
}
