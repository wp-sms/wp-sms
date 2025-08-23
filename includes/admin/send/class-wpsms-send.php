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


    public function ajax_get_recipient_counts()
    {
        check_ajax_referer('wp_rest', 'wpsms_nonce');
        $type  = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';
        $count = 0;

        switch ($type) {
            case 'roles':
                $result = Helper::getUsersMobileNumberCountsWithRoleDetails();
                $count  = $result['roles'][$value]['count'] ?? 0;
                break;

            case 'wc-customers':
                $numbers = Helper::getWooCommerceCustomersNumbers();
                $count   = count($numbers);
                break;

            case 'bp-members':
                if (class_exists('BuddyPress') && class_exists('WP_SMS\Pro\Services\Integration\BuddyPress\BuddyPress')) {
                    $count = \WP_SMS\Pro\Services\Integration\BuddyPress\BuddyPress::getTotalMobileNumbers();
                }
                break;
        }

        wp_send_json_success(['count' => $count]);
    }

    public function ajax_get_user_roles_and_mobile_count()
    {
        check_ajax_referer('wp_rest', 'wpsms_nonce');
        $result = Helper::getUsersMobileNumberCountsWithRoleDetails();

        $roles = [];
        foreach ($result['roles'] as $role_key => $role_data) {
            $roles[] = [
                'id'    => $role_key,
                'name'  => $role_data['name'],
                'count' => $role_data['count']
            ];
        }

        wp_send_json_success([
            'total_mobile_count' => $result['total']['count'],
            'roles'              => $roles
        ]);
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
