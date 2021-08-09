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

        $args = array(
            'headers' => array(
                'X-Mitto-API-Key' => $this->has_key,
                'Content-Type'    => 'application/json',
            ),
            'body'    => json_encode([
                'from' => $this->from,
                'to'   => $this->to,
                'text' => $this->msg,
            ]),
        );

        $response = wp_remote_post($this->wsdl_link . '/smsbulk?format=json', $args);

        if (is_wp_error($response)) {
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $result        = json_decode($response['body']);
        
        if ($response_code != '200' or $result->responseCode !== 0) {
            $this->log($this->from, $this->msg, $this->to, $result->responseText, 'error');
            return new \WP_Error('sms-send', $result->responseText);
        }

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
    }

    public function GetCredit()
    {
        return 1; // todo
    }
}