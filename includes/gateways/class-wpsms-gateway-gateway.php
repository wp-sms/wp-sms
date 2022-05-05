<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class gateway extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.gateway.sa/api/v2/";
    public $tariff = "http://sms.gateway.sa/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    protected $api_type = 'local';
    public $gatewayFields = [
        'api_type' => [
            'id'      => 'gateway_api_type',
            'name'    => 'API type',
            'desc'    => 'Please select what is your API type',
            'type'    => 'select',
            'options' => [
                'local'         => 'Local',
                'international' => 'International'
            ]
        ],
        'username' => [
            'id'   => 'gateway_username',
            'name' => 'API Key / API ID',
            'desc' => 'Enter API Key or API ID of gateway',
        ],
        'password' => [
            'id'   => 'gateway_password',
            'name' => 'API Password',
            'desc' => 'Enter API Password of gateway',
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

        try {

            // Check gateway credit
            $this->GetCredit();

            if ($this->api_type && $this->api_type == 'international') {
                $response = $this->sendSmsWithInternationalApi();
            } else {
                $response = $this->sendSmsWithLocalApi();
            }

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @since 2.4
             */
            do_action('wp_sms_send', $response);

            return $response;

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage());

            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        try {

            if (!$this->username or !$this->password) {
                throw new Exception(__('Username and Password are required.', 'wp-sms'));
            }

            if ($this->api_type && $this->api_type == 'international') {
                $balance = $this->getCreditWithInternationalApi();
            } else {
                $balance = $this->getCreditWithLocalApi();
            }

            return $balance;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

    private function getCreditWithLocalApi()
    {
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

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        if (wp_remote_retrieve_response_code($response) != '200') {
            throw new Exception($response['body']);
        }

        $result = json_decode($response['body'], true);

        if ($result['ErrorCode'] == '0') {
            foreach ($result['Data'] as $data) {
                if ($data['PluginType'] == 'SMS') {
                    return $data['Credits'];
                }
            }
        } else {
            throw new Exception($response['body']);
        }
    }

    private function sendSmsWithLocalApi()
    {
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

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        if (wp_remote_retrieve_response_code($response) != '200') {
            throw new Exception($response['body']);
        }

        $result = json_decode($response['body'], true);

        if ($result['ErrorCode'] == '0') {
            throw new Exception(print_r($result, 1)); // todo, response should be updated in exception
        }

        foreach ($result['Data'] as $data) {
            if ($data['MessageErrorCode'] == '0') {
                return $data;
            } else {
                throw new Exception(print_r($data, 1)); // todo, response should be updated in exception
            }
        }
    }

    private function getCreditWithInternationalApi()
    {
        $arguments = [
            'api_id'       => $this->username,
            'api_password' => $this->password,
        ];

        $response = $this->request('GET', 'https://rest.gateway.sa/api/CheckBalance', $arguments);

        if ($response->Message == 'OK') {
            return $response->BalanceAmount;
        }

        throw new Exception($response->Message);
    }

    private function sendSmsWithInternationalApi()
    {
        $response = $this->request('POST', 'https://rest.gateway.sa/api/SendSMSMulti', [], [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body'    => json_encode([
                'api_id'       => $this->username,
                'api_password' => $this->password,
                'sms_type'     => 'T',
                'encoding'     => isset($this->isflash) ? 'FS' : 'T',
                'sender_id'    => $this->from,
                'textmessage'  => $this->msg,
                'phonenumber'  => implode(',', $this->to)
            ])
        ]);

        if ($response[0]->status == 'F') {
            throw new Exception($response[0]->remarks);
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