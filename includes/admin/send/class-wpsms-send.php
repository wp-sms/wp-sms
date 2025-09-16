<?php

namespace WP_SMS;

use WP_SMS\Components\Ajax;

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
        $credit = false;
        if (isset($this->options['account_credit_in_sendsms']) and !is_object($this->sms::credit()) and !is_array($this->sms::credit())) {
            $credit = $this->sms::credit();
        }

        $args = [
            'get_group_result' => Newsletter::getGroups(),
            'proIsActive'      => Version::pro_is_active(),
            'smsObject'        => $this->sms,
            'gatewayCredit'    => $credit
        ];

        $content = apply_filters('wp_sms_send_sms_page_content', Helper::loadTemplate('admin/send-sms.php', $args), $args);

        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}
