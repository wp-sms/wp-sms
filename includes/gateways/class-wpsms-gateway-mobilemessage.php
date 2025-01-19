<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class mobilemessage extends Gateway
{
    private $wsdl_link      = "https://api.mobilemessage.com.au";
    public $unitrial        = false;
    public $unit;
    public $flash           = "disable";
    public $isflash         = false;
    public $authorization   = '';

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send        = true;
        $this->supportMedia     = false;
        $this->supportIncoming  = true;

        $this->help             = 'The message content can be up to a maximum of <b>765 characters</b>. It supports <b>GSM characters</b>, including standard English letters, numbers, and punctuation. However, <b>emojis are not supported</b>';
        $this->validateNumber   = 'The recipient\'s phone number can be in local Australian format (e.g. 0412345678) or international format (e.g. +61412345678).';

        $this->username         = $this->options['gateway_username'];
        $this->password         = $this->options['gateway_password'];
        $this->from             = $this->options['from'];

        if (!empty($this->username) && !empty($this->password)) {
            $this->authorization = base64_encode($this->username . ':' . $this->password);
        }
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
            if (empty($this->authorization) || empty($this->from)) {
                return new WP_Error('account-credit', 'Please enter the API username and password, and Sender number.');
            }

            $params = [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Basic ' . $this->authorization,
                ],
                'body'    => wp_json_encode([
                    'messages' => [
                        [
                            'to'            => $this->to[0],
                            'message'       => $this->msg,
                            'sender'        => $this->from,
                        ],
                    ],
                ]),
            ];

            $response = $this->request('POST', $this->wsdl_link . '/v1/messages', [], $params);

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
            if (empty($this->authorization)) {
                return new WP_Error('account-credit', 'Please enter the API username and password.');
            }

            $params = [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . $this->authorization,
                ]
            ];

            $response = $this->request('GET', $this->wsdl_link . '/v1/account', [], $params);

            return $response->credit_balance;
        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

}
