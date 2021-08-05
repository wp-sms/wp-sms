<?php

namespace WP_SMS\Gateway;

class mitto extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://rest.mittoapi.com";
    public $tariff = "https://mitto.ch";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $documentUrl = 'https://wp-sms-pro.com/resources/mitto-gateway-configuration/';
    public $gatewayFields = [
        'from'    => [
            'id'   => 'gateway_sender_id',
            'name' => 'Sender number',
            'desc' => 'Sender number or sender ID',
        ],
        'has_key' => [
            'id'   => 'gateway_key',
            'name' => 'X-Mitto-API-Key',
            'desc' => 'Your API key. You must include it for every request to send an SMS. Contact Mitto Support to get set up with one.'
        ]
    ];

    public function __construct()
    {
        parent::__construct();
        $this->has_key        = true;
        $this->validateNumber = "The number to which the message is sent. Numbers are specified in E.164 format.";
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

        $body = [];
        foreach ($this->to as $number) {
            $body[] = [
                'from' => $this->from,
                'to'   => $number,
                'text' => $this->msg,
            ];
        }

        $args = array(
            'headers' => array(
                'X-Mitto-API-Key' => $this->has_key,
                'Content-Type'    => 'application/json',
            ),
            'body'    => json_encode($body),
        );

        $response = wp_remote_post($this->wsdl_link . '/smsbulk', $args);
        
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $result        = json_decode($response['body']);

        if ($response_code == '202') {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $result);

            return $result;
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result->errorDescription, 'error');

            return new \WP_Error('sms-send', $result->errorDescription);
        }
    }

    public function GetCredit()
    {
        return 1; // todo

        // Check username and password
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('X-Mitto-API-Key are required.', 'wp-sms-pro'));
        }
    }
}