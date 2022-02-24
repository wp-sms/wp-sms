<?php

namespace WP_SMS\Gateway;

class globalvoice extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://rest.global-voice.net/rest/";
    public $tariff = "https://global-voice.net/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $gatewayFields = [
        'from'    => [
            'id'   => 'gateway_sender_id',
            'name' => 'Sender number',
            'desc' => 'Sender number or sender ID',
        ],
        'has_key' => [
            'id'   => 'gateway_key',
            'name' => 'Token',
            'desc' => 'Enter Token of gateway'
        ]
    ];

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

        $args = array(
            'timeout' => 10,
            'headers' => array(
                'accept'        => 'application/json',
                'authorization' => 'Bearer ' . $this->has_key,
            ),
            'body'    => array(
                'from'    => $this->from,
                'to'      => implode(',', $this->to),
                'message' => $this->msg,
            )
        );

        $response = wp_remote_post($this->wsdl_link . 'send_sms', $args);

        // Check gateway credit
        if (is_wp_error($response)) {
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');
            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Decode response
        $response = json_decode($response['body']);

        if (isset($response->error)) {
            $this->log($this->from, $this->msg, $this->to, $response->error, 'error');
            return new \WP_Error('send-sms', $response->message);
        }

        if (isset($response->error_message)) {
            $this->log($this->from, $this->msg, $this->to, $response->error_message, 'error');
            return new \WP_Error('send-sms', $response->error_message);
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
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('Token is not entered.', 'wp-sms'));
        }

        $args     = array(
            'timeout' => 10,
            'headers' => array(
                'accept'        => 'application/json',
                'authorization' => 'Bearer ' . $this->has_key,
            )
        );
        $response = wp_remote_get($this->wsdl_link . "account", $args);

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        // Decode response
        $response = json_decode($response['body']);

        if (isset($response->error_message)) {
            return new \WP_Error('account-credit', $response->error_message);
        }

        // Check response code
        if (isset($response[0]->balance)) {
            return $response[0]->balance . ' ' . $response[0]->currency_code;
        } else {
            return new \WP_Error('account-credit', $response);
        }
    }
}