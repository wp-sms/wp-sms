<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class hellosms extends Gateway
{
    private $wsdl_link      = "https://api.hellosms.se";
    public $unitrial        = false;
    public $unit;
    public $flash           = "disable";
    public $isflash         = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send        = true;
        $this->supportMedia     = false;
        $this->supportIncoming  = true;

        $this->help = 'API username and password can be retrieved from <a href="https://dashboard.hellosms.se/dashboard-api/" target="_blank">HelloSMS Dashboard → API</a>.';
        $this->validateNumber = 'Recommended format: international (+46700000000). Other formats, such as without the country code (e.g., 0700000000), are supported. If the country code is omitted, the system will assume the number belongs to the account’s registered country.';
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
            if (empty($this->username) || empty($this->password)) {
                throw new Exception('Please enter the API username and password.');
            }

            $params = [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                ],
                'body'    => wp_json_encode([
                    'to'        => $this->to,
                    'from'      => $this->from,
                    'message'   => $this->msg
                ]),
            ];

            $response = $this->request('POST', $this->wsdl_link . '/v1/sms/send/', [], $params);

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
                throw new Exception('Please enter the API username and password.');
            }

            $params = [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                ]
            ];

            $response = $this->request('GET', $this->wsdl_link . '/v1/account/balance/', [], $params);

            return $response->credits;
        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

}
