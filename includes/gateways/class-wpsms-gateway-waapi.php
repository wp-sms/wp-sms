<?php

namespace WP_SMS\Gateway;

use WP_Error;

class waapi extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://apiv3.waapi.co/api";
    public $tariff = "https://www.waapi.co/pricing";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "Example: 919374512345";
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

        $errors  = [];
        $success = [];
        foreach ($this->to as $number) {
            $argument = add_query_arg([
                'client_id' => $this->username,
                'instance'  => $this->password,
                'type'      => 'text',
                'message'   => $this->msg,
                'number'    => $number,
            ], $this->wsdl_link . '/send.php');

            $response = wp_remote_get($argument, ['timeout' => 10]);

            if (is_wp_error($response)) {
                $errors[$number] = $response->get_error_message();
                continue;
            }

            if (200 != wp_remote_retrieve_response_code($response)) {
                $errors[$number] = $response['body'];
                continue;
            }

            $body = json_decode($response['body']);
            if (isset($body->error)) {
                $errors[$number] = $body->message . 'Error code: ' . $body->error;
                continue;
            }

            $success[] = $response['body'];
        }

        if ($errors) {
            $this->log($this->from, $this->msg, $this->to, $errors, 'error');
            return new WP_Error('send-sms', print_r($errors, 1));
        }

        $this->log($this->from, $this->msg, $this->to, $success);

        /**
         * Run hook after send sms.
         *
         * @param string $result result output.
         * @since 2.4
         *
         */
        do_action('wp_sms_send', $success);

        return $success;
    }

    public function GetCredit()
    {
        $argument = add_query_arg([
            'client_id' => $this->username,
            'instance'  => $this->password,
        ], $this->wsdl_link . '/checkconnection.php');

        $response = wp_remote_get($argument);

        if (is_wp_error($response)) {
            return $response;
        }

        if (200 != wp_remote_retrieve_response_code($response)) {
            return new WP_Error('account-credit', $response['body']);
        }

        $body = json_decode($response['body']);

        if (isset($body->error)) {
            return new WP_Error('account-credit', $body->message);
        }

        return $body->message;
    }
}