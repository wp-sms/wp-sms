<?php

namespace WP_SMS\Gateway;

class uwaziimobile extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://restapi.uwaziimobile.com/v1";
    public $tariff = "http://uwaziimobile.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $gatewayFields = [
        'username' => [
            'id'   => 'gateway_username',
            'name' => 'Token',
            'desc' => 'Enter your Token',
        ],
        'from'     => [
            'id'   => 'gateway_sender_id',
            'name' => 'Sender number',
            'desc' => 'Sender number or sender ID',
        ],
    ];

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
                'X-Access-Token' => $this->username,
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

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Decode response
        $response = json_decode($response['body'], true);

        if ($response['error_code']) {
            return new \WP_Error('account-credit', $response['errors']);
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
        // Check username and password
        if (!$this->username) {
            return new \WP_Error('account-credit', __('API Key is not entered.', 'wp-sms'));
        }

        $response = wp_remote_get(sprintf('%s/me', $this->wsdl_link), [
            'timeout' => 10,
            'headers' => [
                'Content-Type'   => 'application/json',
                'Accept'         => 'application/json',
                'X-Access-Token' => $this->username,
            ]
        ]);

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        // Decode response
        $response = json_decode($response['body'], true);

        if ($response['error_code']) {
            return new \WP_Error('account-credit', $response['errors']);
        }

        return $response;
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