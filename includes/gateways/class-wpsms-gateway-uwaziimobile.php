<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class uwaziimobile extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://restapi.uwaziimobile.com/v1";
    public $tariff = "http://uwaziimobile.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "Destination addresses must be in international format (Example: 254722123456).";
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

        // Get the credit.
        $credit = $this->GetCredit();

        // Check gateway credit
        if (is_wp_error($credit)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return $credit;
        }

        $country_code  = isset($this->options['mobile_county_code']) ? $this->options['mobile_county_code'] : '';
        $mobileNumbers = array_map(function ($item) use ($country_code) {
            return $this->clean_number($item, $country_code);
        }, $this->to);

        $response = wp_remote_post(sprintf('%s/send', $this->wsdl_link), [
            'timeout' => 30,
            'headers' => array(
                'Content-Type'   => 'application/json',
                'Accept'         => 'application/json',
                'X-Access-Token' => $this->getAccessToken(),
            ),
            'body'    => json_encode([
                'number'   => $mobileNumbers,
                'senderID' => $this->from,
                'text'     => $this->msg,
                'type'     => 'sms',
            ])
        ]);

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new WP_Error('send-sms', $response->get_error_message());
        }

        // Decode response
        $response = json_decode($response['body'], true);

        if ($response['error_code']) {
            return new WP_Error('account-credit', $response['errors']);
        }

        // Log the result
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
    }

    public function GetCredit()
    {
        try {
            // Check username and password
            if (!$this->username or !$this->password) {
                throw new Exception(__('API Key is not entered.', 'wp-sms'));
            }

            $response = wp_remote_get(sprintf('%s/me', $this->wsdl_link), [
                'timeout' => 10,
                'headers' => [
                    'Content-Type'   => 'application/json',
                    'Accept'         => 'application/json',
                    'X-Access-Token' => $this->getAccessToken(),
                ]
            ]);

            // Check gateway credit
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            // Decode response
            $response = json_decode($response['body'], true);

            if ($response['error_code']) {
                throw new Exception($response['errors']);
            }

            return $response;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

    private function getAccessToken()
    {
        if (get_option('wpsms_gateway_uwaziimobile_access_token')) {
            return get_option('wpsms_gateway_uwaziimobile_access_token');
        }

        $authorizeResponse = $this->executeRequest('authorize', [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if ($authorizeResponse['???']) { // todo
            $accessTokenResponse = $this->executeRequest('accesstoken', [
                'authorization_code' => $this->username,
                'password'           => $authorizeResponse['???'] // todo
            ]);

            if ($accessTokenResponse['???']) { // todo
                update_option('wpsms_gateway_uwaziimobile_access_token', $accessTokenResponse['???']);

                return $accessTokenResponse['???']; // todo
            }
        }

        return false;
    }

    private function executeRequest($endpoint, $params = [], $method = 'POST')
    {
        $response = wp_remote_post(sprintf('%s/%s', $this->wsdl_link, $endpoint), [
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body'    => json_encode($params)
        ]);

        // Check gateway credit
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        // Decode response
        $responseArray = json_decode($response['body'], true);

        if (isset($responseArray['errors'])) {
            throw new Exception(print_r($responseArray['errors'], 1));
        }

        return $responseArray;
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

        //Remove 00 in the begining
        if (substr($number, 0, 2) == "00") {
            $number = substr($number, 2, strlen($number));
        }

        //Remove 00 in the begining
        if (substr($number, 0, 1) == "0") {
            $number = substr($number, 1, strlen($number));
        }

        $number = $country_code . $number;

        return $number;
    }
}