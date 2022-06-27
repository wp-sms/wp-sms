<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class aobox extends \WP_SMS\Gateway
{
    private $wsdl_link = " https://aobox.it/app";
    public $tariff = "https://www.aobox.it";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $gateway_route;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->validateNumber = "Recipient number(s) with international prefix without “+”. example: 393351234567,393337654321,393880000123";
        $this->help           = "Just enter your username and password.";
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
                'name' => 'Sender',
                'desc' => 'The SMS sender, maximum 11 characters. If not set “AObox” will be used as sender. Note: some routes do not allow a free sender and some routes only allow a fixed sender. ',
            ],
            'route'    => [
                'id'   => 'gateway_route',
                'name' => 'Route',
                'desc' => 'The gateway route via which the SMS is sent. Different routes have different features and quality. Route numbers you can use are supplied by your sales account.',
            ],
        ];
    }

    public function SendSMS(): WP_Error|string
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

            // Get the credit.
            $credit = $this->GetCredit();

            // Check gateway credit
            if (is_wp_error($credit)) {
                throw new Exception($credit->get_error_message());
            }

            $params = [
                'version'  => '3',
                'username' => $this->username,
                'password' => $this->password,
                'route'    => $this->gateway_route ?? '3',
                'sender'   => $this->from ?? null,
                'rcpt'     => implode(',', $this->to),
                'text'     => $this->msg,
            ];

            $response = $this->request('POST', "$this->wsdl_link/gateway.php", $params);

            if (isset($response->statuscode) && $response->statuscode !== 0) {
                throw new Exception($response);
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
            if (!$this->username or !$this->password) {
                throw new Exception(__('The username/password for this gateway is not set.', 'wp-sms'));
            }

            $params = [
                'username' => $this->username,
                'password' => $this->password,
            ];

            return $this->request('POST', "$this->wsdl_link/getcred3.php", $params);

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            return new WP_Error('account-credit', $error_message);
        }
    }
}