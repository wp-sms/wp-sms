<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class _160au extends Gateway
{
    private $wsdl_link = "https://www.160.com.au/api/sms.asmx/";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send       = true;
        $this->has_key         = false;
        $this->supportIncoming = true;
        $this->validateNumber  = "Number of the recipient with country code (eg: +61000000000)";
        $this->help            = "The mobile number must include the <b>country code</b>";
        $this->gatewayFields   = [
            'username' => [
                'id'   => 'username',
                'name' => 'Username',
                'desc' => 'Enter your username.',
            ],
            'password' => [
                'id'   => 'password',
                'name' => 'Password',
                'desc' => 'Enter your password.',
            ],
            'from'     => [
                'id'   => 'from',
                'name' => 'Sender number',
                'desc' => 'Sender number or sender ID',
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
            $balance = $this->GetCredit();

            if (is_wp_error($balance)) {
                throw new Exception($balance->get_error_message());
            }

            $params = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body'    => [
                    'username'    => $this->username,
                    'password'    => $this->password,
                    'messageText' => $this->msg,
                ],
            ];

            if (!empty($this->from)) {
                $params['body']['senderName'] = $this->from;
            }

            // Conversion for bulk sending
            if (count($this->to) > 1) {
                foreach ($this->to as $number) {
                    $params['body']["mobileNumber[{$number}]"] = $number;
                }
            } else {
                $params['body']['mobileNumber'] = $this->to[0];
            }

            $response = $this->request('POST', $this->wsdl_link . 'SendMessage', [], $params, false);

            $response = @(array)simplexml_load_string($response);

            if (strpos($response[0], 'ERR:') === 0) {
                throw new Exception($response[0]);
            }

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

            if (empty($this->username) || empty($this->password)) {
                return new WP_Error('account-credit', 'Please enter your username and password.');
            }

            $params = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body'    => [
                    'username' => $this->username,
                    'password' => $this->password,
                ],
            ];

            $response = $this->request('POST', $this->wsdl_link . 'GetCreditBalance', [], $params, false);

            $response = @(array)simplexml_load_string($response);

            if (strpos($response[0], 'ERR:') === 0) {
                throw new Exception($response[0]);
            }

            return $response[0];
        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

}
