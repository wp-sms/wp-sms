<?php

namespace WP_SMS\Gateway;

class uwaziimobile extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://107.20.199.106/";
    public $tariff = "http://uwaziimobile.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "Destination addresses must be in international format (Example: 254722123456).";
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

        // Reformat number
        $to = array();
        foreach ($this->to as $number) {
            if (substr($number, 0, 2) === "07") {
                $number = substr($number, 2);
                $number = '2547' . $number;
            }

            $to[] = $number;
        }

        $args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'accept'        => 'application/json',
                'authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
            ),
            'body'    => json_encode(array(
                'messages' => array(
                    array(
                        'from' => $this->from,
                        'to'   => $to,
                        'text' => $this->msg,
                    )
                )
            ))
        );

        $response = wp_remote_post($this->wsdl_link . "restapi/sms/1/text/multi", $args);

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Ger response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Decode response
        $response = json_decode($response['body']);

        // Check response code
        if ($response_code == '200') {
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
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->requestError->serviceException->text, 'error');

            return new \WP_Error('account-credit', $response->requestError->serviceException->text);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username or !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms-pro'));
        }

        $args     = array(
            'timeout' => 10,
            'headers' => array(
                'accept'        => 'application/json',
                'authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
            )
        );
        $response = wp_remote_get($this->wsdl_link . "restapi/account/1/balance", $args);

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        // Ger response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Decode response
        $response = json_decode($response['body']);

        // Check response code
        if ($response_code == '200') {
            return $response->balance;
        } else {
            return new \WP_Error('account-credit', $response->requestError->serviceException->text);
        }

        return true;
    }
}