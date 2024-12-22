<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class sms extends Gateway
{
    private $wsdl_link = "https://api.sms.ir/v1/";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $gateway_key;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send       = true;
        $this->supportMedia    = false;
        $this->supportIncoming = false;
        $this->gatewayFields   = [
            'gateway_key' => [
                'id'   => 'gateway_key',
                'name' => __('API Key', 'wp-sms'),
                'desc' => __('Enter your API KEY', 'wp-sms'),
            ],
            'from'        => [
                'id'   => 'from',
                'name' => __('Sender Number', 'wp-sms'),
                'desc' => __('Enter your Sender Number/Name', 'wp-sms'),
            ],
        ];
    }

    public function SendSMS()
    {

        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         *
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         *
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         *
         * @since 3.4
         *
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {
            if (empty($this->gateway_key) || empty($this->from)) {
                return new WP_Error('account-credit', 'Please enter your API KEY and Sender Number.');
            }

            $params = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'ACCEPT'       => 'application/json',
                    'X-API-KEY'    => $this->gateway_key,
                ],
                'body'    => wp_json_encode([
                    'lineNumber'  => $this->from,
                    'messageText' => $this->msg,
                    'mobiles'     => $this->to,
                ])
            ];

            $response = $this->request('POST', $this->wsdl_link . 'send/bulk', [], $params);

            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             *
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

            return $response;

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }

    }

    public function GetCredit()
    {
        try {
            if (empty($this->gateway_key) || empty($this->from)) {
                return new WP_Error('account-credit', 'Please enter your API KEY and Sender Number.');
            }

            $params = [
                'headers' => [
                    'ACCEPT'    => 'application/json',
                    'X-API-KEY' => $this->gateway_key,
                ]
            ];

            $response = $this->request('GET', $this->wsdl_link . 'credit', [], $params);

            return $response->data;
        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

}
