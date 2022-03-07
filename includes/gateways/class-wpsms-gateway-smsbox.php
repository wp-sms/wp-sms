<?php

namespace WP_SMS\Gateway;

class smsbox extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://core.smsbox.be/api/v1/";
    public $tariff = "https://smsbox.be";
    public $unitrial = false;
    public $unit;
    public $flash = "disabled";
    public $isflash = false;
    public $documentUrl = 'https://wp-sms-pro.com/resources/smsbox-gateway-configuration/';
    public $gatewayFields = [
        'from'    => [
            'id'   => 'gateway_sender_id',
            'name' => 'Sender number',
            'desc' => 'Sender number or sender ID',
        ],
        'has_key' => [
            'id'   => 'gateway_key',
            'name' => 'API Token',
            'desc' => 'SMSBOX apikey, you can create this apikey by logging into your <a href="https://www.jaan.be/smsbox/sms-gateway-api">SMSBOX account</a>.'
        ]
    ];
    public $validateNumber = 'mobiele nummers gescheiden door een komma (countrycode+nummer)';

    public function __construct()
    {
        parent::__construct();
        $this->has_key = true;
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

        // Get the credit.
        $credit = $this->GetCredit();

        // Check gateway credit
        if (is_wp_error($credit)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return $credit;
        }

        $country_code = isset($this->options['mobile_county_code']) ? $this->options['mobile_county_code'] : '';
        $recipients   = [];
        foreach ($this->to as $to) {
            $recipients[] = $this->clean_number($to, $country_code);
        }
        $this->to = $recipients;

        try {
            $response      = wp_remote_post($this->wsdl_link . 'sendsms', [
                'headers' => [
                    'X-Api-Key'    => urlencode($this->has_key),
                    'Content-Type' => 'application/json'
                ],
                'body'    => json_encode([
                    'from'    => $this->from,
                    'numbers' => implode(', ', $this->to),
                    'message' => urlencode($this->msg),
                    'unicode' => isset($this->options['send_unicode']) ? 1 : 0,
                ])
            ]);
            $response_code = wp_remote_retrieve_response_code($response);

            // Check response error
            if (is_wp_error($response)) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

                return new \WP_Error('send-sms', $response->get_error_message());
            }


            $body   = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);

            if (empty($body)) {
                // Log th result
                $this->log($this->from, $this->msg, $this->to, $body, 'error');

                return false;
            }

            if ($response_code == 200 && isset($result['code']) && $result['code'] == '100') {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $body);

                /**
                 * Run hook after send sms.
                 *
                 * @since 2.4
                 */
                do_action('wp_sms_send', $body);

                return $body;
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result['message'], 'error');

                return new \WP_Error('send-sms', $result['message']);
            }
        } catch (\Exception $e) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('The API Key for this gateway is not set', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . 'balance', [
            'headers' => [
                'X-Api-Key'    => urlencode($this->has_key),
                'Content-Type' => 'application/json'
            ],
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $result = $response['body'];
            $result = json_decode($result, true);
            if ($result['code'] == '100') {
                return $result['message'];
            }
            return new \WP_Error('account-credit', $result['message']);
        } else {
            return new \WP_Error('account-credit', $response['body']);
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
