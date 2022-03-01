<?php

namespace WP_SMS\Api\V1;

use WP_SMS\Admin\Helper;
use WP_SMS\Gateway;
use WP_SMS\Option;
use WP_SMS\Pro\Scheduled;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * @category   class
 * @package    WP_SMS_Api
 * @version    1.0
 */
class Send extends \WP_SMS\RestApi
{
    private $sendSmsArguments = [
        'sender'     => array('required' => true, 'type' => 'string'),
        'recipients' => array('required' => true, 'type' => 'string', 'enum' => ['subscribers', 'users', 'wc-customers', 'bp-users', 'role', 'numbers']),
        'group_id'   => array('required' => false, 'type' => 'number'),
        'role_id'    => array('required' => false, 'type' => 'number'),
        'numbers'    => array('required' => false, 'type' => 'array', 'format' => 'uri'),
        'message'    => array('required' => true, 'type' => 'string'),
        'flash'      => array('required' => false, 'type' => 'boolean'),
        'media_urls' => array('required' => false, 'type' => 'array'),
        'schedule'   => array('required' => false, 'type' => 'string', 'format' => 'date-time')
    ];

    public function __construct()
    {
        // Register routes
        add_action('rest_api_init', array($this, 'register_routes'));

        parent::__construct();
    }

    /**
     * Register routes
     */
    public function register_routes()
    {
        register_rest_route($this->namespace . '/v1', '/send', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'send_callback'),
                'args'                => $this->sendSmsArguments,
                'permission_callback' => array($this, 'get_item_permissions_check'),
            )
        ));
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return \WP_REST_Response
     */
    public function send_callback(\WP_REST_Request $request)
    {
        // Get parameters from request
        $params = $request->get_params();

        return self::response(['Is test']);

        // todo
        if ($_POST['wp_send_to'] == "wp_subscribe_username") {

            if (empty($_POST['wpsms_groups'])) {
                $this->sms->to = $this->db->get_col("SELECT mobile FROM {$this->db->prefix}sms_subscribes WHERE `status` = '1'");
            } else {
                $groups        = implode(',', wp_sms_sanitize_array($_POST['wpsms_groups']));
                $this->sms->to = $this->db->get_col("SELECT mobile FROM {$this->db->prefix}sms_subscribes WHERE `status` = '1' AND `group_ID` IN (" . $groups . ")");
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
        $this->sms->msg  = sanitize_textarea_field($_POST['wp_get_message']);

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


        $to      = isset ($params['to']) ? $params['to'] : '';
        $msg     = isset ($params['msg']) ? $params['msg'] : '';
        $isflash = isset ($params['isflash']) ? $params['isflash'] : '';
        $result  = self::sendSMS($to, $msg, $isflash);

        if (is_wp_error($result)) {
            return self::response($result->get_error_message(), 400);
        }

        $gateway_name   = Option::getOption('gateway_name');
        $credit         = get_option('wpsms_gateway_credit');
        $successMessage = __('The SMS sent successfully', 'wp-sms');
    }

    /**
     * Check user permission
     *
     * @param $request
     *
     * @return bool
     */
    public function get_item_permissions_check($request)
    {
        return current_user_can('wpsms_sendsms');
    }
}

new Send();