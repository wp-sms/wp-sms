<?php

namespace WP_SMS\Gateway;

class gateway extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.gateway.sa/api/v2/";
    public $tariff = "http://sms.gateway.sa/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    public $gatewayFields = [
        'username' => [
            'id'   => 'gateway_username',
            'name' => 'API Key',
            'desc' => 'Enter API Key of gateway',
        ],
        'password' => [
            'id'   => 'gateway_password',
            'name' => 'Client Id',
            'desc' => 'Enter Client Id of gateway',
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
        $this->validateNumber = "+966556xxxxxx";
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

        $country_code  = isset($this->options['mobile_county_code']) ? $this->options['mobile_county_code'] : '';
        $mobileNumbers = array_map(function ($item) use ($country_code) {
            return $this->clean_number($item, $country_code);
        }, $this->to);

        $Is_Flash   = ($this->isflash ? true : false);
        $Is_Unicode = ((isset($this->options['send_unicode']) and $this->options['send_unicode']) ? true : false);

        $response = wp_remote_get($this->wsdl_link . 'SendSMS', [
            'timeout' => 30,
            'headers' => [
                'Content-Type:application/json',
            ],
            'body'    => [
                'SenderId'      => $this->from,
                'Is_Unicode'    => $Is_Unicode,
                'Is_Flash'      => $Is_Flash,
                'Message'       => $this->msg,
                'MobileNumbers' => implode(',', $mobileNumbers),
                'ApiKey'        => $this->username,
                'ClientId'      => $this->password,
            ]
        ]);

        $response_code = wp_remote_retrieve_response_code($response);

        // Check response error
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        if ($response_code == '200') {

            $result = json_decode($response['body'], true);

            if ($result['ErrorCode'] == '0') {
                foreach ($result['Data'] as $data) {
                    if ($data['MessageErrorCode'] == '0') {

                        // Log the result
                        $this->log($this->from, $this->msg, $this->to, $data);

                        /**
                         * Run hook after send sms.
                         *
                         * @since 2.4
                         */
                        do_action('wp_sms_send', $data);

                        return $result;
                    } else {
                        // Log the result
                        $this->log($this->from, $this->msg, $this->to, $data, 'error');

                        return new \WP_Error('send-sms', $data);
                    }
                }
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $data, 'error');

                return new \WP_Error('send-sms', $data);
            }
        } else {

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . 'Balance', [
            'timeout' => 30,
            'headers' => [
                'Content-Type:application/json'
            ],
            'body'    => [
                'ApiKey'   => $this->username,
                'ClientId' => $this->password,
            ]
        ]);

        if (!is_wp_error($response)) {
            $result = json_decode($response['body'], true);
            if ($result['ErrorCode'] == '0') {
                foreach ($result['Data'] as $data) {
                    if ($data['PluginType'] == 'SMS') {
                        return $data['Credits'];
                    }
                }
            } else {
                return new \WP_Error('account-credit', $response['body']);
            }
        } else {
            return new \WP_Error('account-credit', $response->get_error_message());
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