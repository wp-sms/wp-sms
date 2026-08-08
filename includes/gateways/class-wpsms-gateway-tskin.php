<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) {
    exit;
}

class tskin extends \WP_SMS\Gateway
{
    private $apiEndpoint = 'https://app.tskin.sa/api/v1/send-message.php';

    public $tariff = 'https://tskin.sa/';
    public $unitrial = false;
    public $unit;
    public $flash = 'disabled';
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();

        $this->bulk_send      = false;
        $this->has_key        = true;
        $this->validateNumber = __('Enter the recipient number with its country code and no leading plus sign, for example 966500000000.', 'wp-sms');
        $this->documentUrl    = 'https://app.tskin.sa/wpplugin/assets/api-docs.php';
        $this->gatewayFields  = [
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => __('API Key', 'wp-sms'),
                'type' => 'password',
                'desc' => __('Enter the API key from your TSKIN dashboard. The key is sent only in the secure Authorization header.', 'wp-sms'),
            ],
        ];
    }

    public function SendSMS()
    {
        $this->from = apply_filters('wp_sms_from', $this->from);
        $this->to   = apply_filters('wp_sms_to', $this->to);
        $this->msg  = apply_filters('wp_sms_msg', $this->msg);

        $params = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->has_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode([
                'phone'        => $this->to[0],
                'message_type' => 'text',
                'message'      => $this->msg,
            ]),
        ];

        try {
            $response = $this->request('POST', $this->apiEndpoint, [], $params);

            $this->log($this->from, $this->msg, $this->to, $response);
            do_action('wp_sms_send', $response);

            return $response;
        } catch (\Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        if (empty($this->has_key)) {
            return new \WP_Error('account-credit', __('TSKIN API Key is required.', 'wp-sms'));
        }

        return true;
    }
}
