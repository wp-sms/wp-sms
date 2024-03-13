<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class signalads extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://panel.signalads.com/rest/api/v1";
    public $tariff = "https://signalads.com";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->has_key        = true;
        $this->bulk_send      = true;
        $this->validateNumber = "+XXXXXXXXXXXXX";
        $this->help           = "";
        $this->gatewayFields  = [
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'Enter your API key.'
            ],
            'from'    => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender Number',
                'desc' => 'Enter the SMS Sender number.',
            ],
        ];
    }

    public function SendSMS()
    {
        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         * @since 3.4
         *
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {

            $arguments = [
                'headers' => array(
                    'Authorization' => "Bearer $this->has_key"
                ),
                'body'    => array(
                    'numbers' => $this->to,
                    'from'    => $this->from,
                    'message' => $this->msg
                )
            ];

            $response = $this->request('GET', "$this->wsdl_link/message/send.json", [], $arguments);

            if (!isset($response->success)) {
                return new Exception($response);
            }

            // Log the result
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

            // Check API key
            if (!$this->has_key) {
                return new WP_Error('account-credit', esc_html__('The API Key is required.', 'wp-sms'));
            }

            $arguments = [
                'headers' => array(
                    'Authorization' => "Bearer $this->has_key"
                )
            ];

            $response = $this->request('GET', "$this->wsdl_link/user/credit.json", [], $arguments);

            if (!isset($response->success)) {
                return new Exception($response);
            }

            if (!isset($response->data->credit)) {
                return $response;
            }

            return $response->data->credit;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }
}