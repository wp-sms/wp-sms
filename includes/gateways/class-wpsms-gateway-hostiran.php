<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class hostiran extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://rest.payamak-panel.com/api/SendSMS";
    public $tariff = "http://sms.hostiran.net";
    public $unitrial = true;
    public $unit;
    public $flash = "false";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->help           = "";
        $this->validateNumber = "09xxxxxxxx";
        $this->has_key        = true;
        $this->bulk_send      = true;
        $this->gatewayFields  = [
            'username' => [
                'id'   => 'gateway_username',
                'name' => 'Username',
                'desc' => 'Enter your username',
            ],
            'password' => [
                'id'   => 'gateway_password',
                'name' => 'Password',
                'desc' => 'Enter your password',
            ],
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'API Token',
                'desc' => 'Enter your API token.',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'Enter your sender ID',
            ],
        ];

        @ini_set("soap.wsdl_cache_enabled", "0");
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
                'headers' => [
                    'token' => $this->has_key
                ],
                'body'    => [
                    'username' => $this->username,
                    'password' => $this->password,
                    'to'       => implode(',', $this->to),
                    'from'     => $this->from,
                    'text'     => $this->msg
                ]
            ];

            $response = $this->request('POST', "{$this->wsdl_link}/SendSMS", [], $arguments);

            if ($response->StrRetStatus !== 'Ok') {
                throw new Exception($response->StrRetStatus);
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
                return new \WP_Error('account-credit', esc_html__('Username and Password are required.', 'wp-sms'));
            }

            $arguments = [
                'headers' => [
                    'token' => $this->has_key
                ],
                'body'    => [
                    'username' => $this->username,
                    'password' => $this->password
                ]
            ];

            $response = $this->request('POST', "{$this->wsdl_link}/GetCredit", [], $arguments);

            if ($response->StrRetStatus !== 'Ok') {
                throw new Exception($response->StrRetStatus);
            }

            $credit = $response->Value;
            return $credit;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }
}