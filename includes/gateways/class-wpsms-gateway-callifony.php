<?php

namespace WP_SMS\Gateway;

class callifony extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://push.globalsms.ae";
    public $tariff = "https://callifony.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $has_key = false;
    public $do = false;
    public $documentUrl = 'https://wp-sms-pro.com/resources/callifony-gateway-configuration/';

    public function __construct()
    {
        parent::__construct();
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
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');
            return $credit;
        }

        $postBody = [
            'source'      => $this->from,
            'destination' => implode(',', $this->to),
            'text'        => $this->msg,
            'dataCoding'  => 1,
        ];

        if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
            $postBody['dataCoding'] = 8;
        }

        $args = [
            'headers' => [
                'token'        => $this->has_key,
                'Content-Type' => 'application/json'
            ],
            'body'    => json_encode($postBody),
        ];

        $response = wp_remote_post("{$this->wsdl_link}/HTTP/api/Client/SendSMS?username={$this->username}&password={$this->password}", $args);
        $response = json_decode($response['body']);

        if ($response->ErrorCode !== 0) {
            $this->log($this->from, $this->msg, $this->to, $this->getErrorMessageByErrorCode($response->ErrorCode), 'error');
            return new \WP_Error('send-sms', $this->getErrorMessageByErrorCode($response->ErrorCode));
        }

        // Log the result
        $this->log($this->from, $this->msg, $this->to, $response);

        /**
         * Run hook after send sms.
         *
         * @param string $result result output.
         * @since 2.4
         *
         */
        do_action('wp_sms_send', $response);

        return $response;
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $response = wp_remote_get("https://access.globalsms.ae/OnlineApi/api/Billing?username={$this->username}&password={$this->password}&isEnterprise=false");

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response = json_decode($response['body']);

        if (isset($response->Balance)) {
            return $response->Balance;
        }
    }

    public function getErrorMessageByErrorCode($errorCode)
    {
        switch ($errorCode) {
            case '-1':
                return 'No Text Message specified';
            case '-2':
                return 'No Source';
            case '-3':
                return 'No Destination';
            case '-4':
                return 'Invalid Destination';
            case '-5':
                return 'Invalid Credentials';
            case '-6':
                return 'No Credit';
            case '-7':
                return 'Invalid Data Coding';
            case '-8':
                return 'IP Not Whitelisted ';
            case '-10':
                return 'Unknown Error';
            case '-11':
                return 'Invalid Instance Connection';
            default :
                return "Unknown error, Code {$errorCode}";
        }
    }
}