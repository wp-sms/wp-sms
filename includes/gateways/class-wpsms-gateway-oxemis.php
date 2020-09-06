<?php

namespace WP_SMS\Gateway;

use WP_Error;

class oxemis extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.oxisms.com/api/1.0/";
    public $tariff = "https://www.oxemis.com/en/sms/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "Example: 0033601234567";
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

        $argument = add_query_arg(array(
            'api_key'      => $this->username,
            'api_password' => $this->password,
            'message'      => $this->msg,
            'recipients'   => implode(',', $this->to),
            'sender'       => $this->from,
        ), $this->wsdl_link . 'send.php');

        $response = wp_remote_get($argument);

        if (is_wp_error($response)) {
            return $response;
        }

        if (200 != wp_remote_retrieve_response_code($response)) {
            return new WP_Error('account-credit', $response['body']);
        }

        $body = json_decode($response['body']);

        if (!$body->success) {
            $this->log($this->from, $this->msg, $this->to, $body->message, 'error');
            return new WP_Error('account-credit', $body->message);
        }

        $this->log($this->from, $this->msg, $this->to, $body->details);

        /**
         * Run hook after send sms.
         *
         * @param string $result result output.
         * @since 2.4
         *
         */
        do_action('wp_sms_send', $response['body']);

        return $body->details;
    }

    public function GetCredit()
    {
        $argument = add_query_arg(array(
            'api_key'      => $this->username,
            'api_password' => $this->password,
        ), $this->wsdl_link . 'account.php');

        $response = wp_remote_get($argument);

        if (is_wp_error($response)) {
            return $response;
        }

        if (200 != wp_remote_retrieve_response_code($response)) {
            return new WP_Error('account-credit', $response['body']);
        }

        $body = json_decode($response['body']);

        if (!$body->success) {
            return new WP_Error('account-credit', $body->message);
        }

        return $body->details->credit;
    }
}