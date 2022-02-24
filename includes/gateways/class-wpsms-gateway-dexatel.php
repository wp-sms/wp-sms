<?php

namespace WP_SMS\Gateway;

class dexatel extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.dexatel.com/v1";
    public $tariff = "https://dexatel.com";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $has_key = true;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = 'Must be sent in international E.164 format (up to 15 digits allowed), e.g: 12025550150';
        $this->help           = 'Please fill out your TOKEN in the API key field.';
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

        $messages = [];
        foreach ($this->to as $item) {
            $messages[] = ['phone' => $item, 'sender_name' => $this->from, 'message' => $this->msg];
        }

        $args = [
            'headers' => [
                'token'        => $this->has_key,
                'Content-Type' => 'application/json'
            ],
            'body'    => json_encode([
                'messages' => $messages,
            ])
        ];

        $response      = wp_remote_post($this->wsdl_link . '/send/sms/bulk', $args);
        $response_code = wp_remote_retrieve_response_code($response);
        $response      = json_decode($response['body']);

        if ($response_code != '200') {
            return new \WP_Error('send-sms', $response->message);
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
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('API key is not entered.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . '/get_balance', [
            'headers' => [
                'token'        => $this->has_key,
                'Content-Type' => 'application/json'
            ]
        ]);

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response      = json_decode($response['body']);

        if ($response_code != '200') {
            return new \WP_Error('account-credit', $response->message);
        }

        return $response->data->balance;
    }
}