<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class oursms extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.oursms.com/api-a";
    public $tariff = "https://www.oursms.net/";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->has_key        = true;
        $this->validateNumber = "Separate numbers between them with comma ( , ) Numbers must be entered in international format 966500000000 and international messages without 00 or +";
        $this->help           = "";
        $this->gatewayFields  = [
            'username' => [
                'id'   => 'gateway_username',
                'name' => 'Username',
                'desc' => 'Enter your username.',
            ],
            'password' => [
                'id'   => 'gateway_password',
                'name' => 'Password',
                'desc' => 'Enter your password.',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'Enter your Sender ID. Default: oursms',
            ],
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'Enter API key of gateway. You can avail it from your control panel.',
            ]
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

            $arguments = array(
                'body' => array(
                    'username' => $this->username,
                    'token'    => $this->has_key,
                    'src'      => $this->from ?: 'oursms',
                    'dests'    => implode(',', $this->to),
                    'body'     => $this->msg
                )
            );

            // Get Send SMS Response
            $response = $this->request('POST', "{$this->wsdl_link}/msgs", [], $arguments);

            // Error Handler
            if (isset($response['errorCode'])) {
                throw new Exception($response['message']);
            }

            //log the result
            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
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

            // Check username and password
            if (!$this->username && !$this->password) {
                return new WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
            }

            $params = array(
                'username' => $this->username,
                'password' => $this->password
            );

            // Get Credit Response
            $response = $this->request('GET', "{$this->wsdl_link}/billing/credits", $params, []);

            if (isset($response['errorCode'])) {
                throw new Exception($response['message']);
            }

            return $response->credits;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }
}