<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class uwaziimobile extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://restapi.uwaziimobile.com/v1";
    public $tariff = "https://www.uwaziimobile.com";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->validateNumber = "Destination addresses must be in international format (Example: 254722123456).";
        $this->help           = "Enter your Username and Password.";
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

            // Get the credit.
            $credit = $this->GetCredit();

            // Check gateway credit
            if (is_wp_error($credit)) {
                throw new Exception($credit->get_error_message());
            }

            $country_code  = $this->options['mobile_county_code'] ?? '';
            $mobileNumbers = array_map(function ($item) use ($country_code) {
                return $this->clean_number($item, $country_code);
            }, $this->to);

            $token = $this->getToken();

            $body = [
                [
                    "number"   => $mobileNumbers,
                    "senderID" => $this->from,
                    "text"     => $this->msg,
                    "type"     => "sms",
                ]
            ];

            $params = array(
                'headers' => [
                    'X-Access-Token' => $token,
                    'Content-Type'   => 'application/json'
                ],
                'body'    => wp_json_encode($body)
            );

            $response = $this->request('POST', "{$this->wsdl_link}/send", [], $params);

            // check $response validation
            if (isset($response->status) && $response->status != 1) {
                throw new Exception($response->errors);
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
                throw new Exception(__('The username/password is not entered.', 'wp-sms'));
            }

            $token = $this->getToken();

            if (is_wp_error($token)) {
                throw new Exception(__('There is a problem with the username and password provided.', 'wp-sms'));
            }

            $params = [
                'headers' => [
                    'X-Access-Token' => $token
                ]
            ];

            $response = $this->request('GET', "{$this->wsdl_link}/me", [], $params);

            // check $response validation
            if (isset($response->status) && $response->status != 1) {
                throw new Exception($response->errors);
            }

            return $response->data->balance_limit;

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            return new WP_Error('account-credit', $error_message);
        }

    }

    private function clean_number($number, $country_code)
    {
        //Clean Country Code from + or 00
        $country_code = str_replace('+', '', $country_code);

        if (substr($country_code, 0, 2) == "00") {
            $country_code = substr($country_code, 2, strlen($country_code));
        }

        //Remove +
        $number = str_replace('+', '', $number);

        if (substr($number, 0, strlen($country_code) * 2) == $country_code . $country_code) {
            $number = substr($number, strlen($country_code) * 2);
        } else {
            $number = substr($number, strlen($country_code));
        }

        //Remove 00 in the beginning
        if (substr($number, 0, 2) == "00") {
            $number = substr($number, 2, strlen($number));
        }

        //Remove 00 in the beginning
        if (substr($number, 0, 1) == "0") {
            $number = substr($number, 1, strlen($number));
        }

        return $country_code . $number;
    }

    /**
     * This private function returns an access token. First, it checks for existence of
     * the token in the system cache and if there wasn't any tokens, it creates a new one.
     * The token is stored in system cache only for 4 hours.
     *
     * @return mixed|string
     * @throws Exception
     */
    private function getToken()
    {
        // Get any existing copy of uwaziimobile gateway token
        if (false === ($token = get_transient('wpsms_uwazii_token'))) {
            // It wasn't there, so regenerate the data and save the transient

            try {

                // generate request body to authenticate
                $body = [
                    "username" => $this->username,
                    "password" => $this->password
                ];

                $params = [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'body'    => wp_json_encode($body)
                ];

                // get authorization code
                $response = $this->request('POST', "{$this->wsdl_link}/authorize", [], $params);

                // check $response validation
                if (isset($response->status) && $response->status != 1) {
                    throw new Exception($response->errors);
                }

                // generate request body to get Access Token
                $body = [
                    'authorization_code' => $response->data->authorization_code
                ];

                $params = [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'body'    => wp_json_encode($body)
                ];

                // get access token
                $response = $this->request('POST', "{$this->wsdl_link}/accesstoken", [], $params);

                // check $response validation
                if (isset($response->status) && $response->status != 1) {
                    throw new Exception($response->errors);
                }

                $token = $response->data->access_token;

                set_transient('wpsms_uwazii_token', $token, HOUR_IN_SECONDS * 4);

            } catch (Exception $e) {
                return new WP_Error('send-sms', $e->getMessage());
            }
        }

        // Use the data like you would have normally...
        return $token;
    }
}