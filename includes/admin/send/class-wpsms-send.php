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
        if (class_exists('woocommerce') and Version::pro_is_active()) {
            $woocommerceCustomers = \WP_SMS\Helper::getWooCommerceCustomersNumbers();
        }

        $buddyPressMobileNumbers = [];
        if (class_exists('BuddyPress') and class_exists('WP_SMS\Pro\Services\Integration\BuddyPress\BuddyPress')) {
            $buddyPressMobileNumbers = \WP_SMS\Pro\Services\Integration\BuddyPress\BuddyPress::getTotalMobileNumbers();
        }

        $credit = false;
        if (isset($this->options['account_credit_in_sendsms']) and !is_object($this->sms::credit()) and !is_array($this->sms::credit())) {
            $credit = $this->sms::credit();
        }

        $userMobileResult = Helper::getUsersMobileNumberCountsWithRoleDetails();

        $args = [
            'get_group_result'        => Newsletter::getGroups(),
            'get_users_mobile'        => $userMobileResult['total']['count'],
            'proIsActive'             => Version::pro_is_active(),
            'woocommerceCustomers'    => $woocommerceCustomers,
            'buddyPressMobileNumbers' => $buddyPressMobileNumbers,
            'wpsms_list_of_role'      => $userMobileResult['roles'],
            'smsObject'               => $this->sms,
            'gatewayCredit'           => $credit
        ];

        echo Helper::loadTemplate('admin/send-sms.php', $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
