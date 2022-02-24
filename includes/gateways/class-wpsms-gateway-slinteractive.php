<?php

namespace WP_SMS\Gateway;

class slinteractive extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://www.slinteractive.com.au/api";
    public $tariff = "https://slinteractive.com.au";
    public $documentUrl = 'https://wp-sms-pro.com/resources/sl-interactive-gateway-configuration/';
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "Example: 61408000111, 61408000112, 614080001113";
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

        if (is_wp_error($credit)) {
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');
            return $credit;
        }

        $request = add_query_arg([
            'uname' => $this->username,
            'pword' => $this->password,
            'to'    => implode(',', $this->to),
            'msg'   => urlencode($this->msg),
            'sid'   => $this->from,
        ], $this->wsdl_link . '/send_sms.php');

        $response = wp_remote_get($request);

        if (is_wp_error($response)) {
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');
            return $response;
        }

        if (wp_remote_retrieve_response_code($response) != '200') {
            return new \WP_Error('send-sms', $response['body']);
        }

        $response = $response['body'];
        if (strstr($response, 'E:Error')) {
            $this->log($this->from, $this->msg, $this->to, $response, 'error');
            return new \WP_Error('send-sms', $response);
        }

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
        if (!$this->username or !$this->password) {
            return new \WP_Error('account-credit', __('API username or API Key is not entered.', 'wp-sms'));
        }

        return 1;
    }
}