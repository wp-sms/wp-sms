<?php

namespace WP_SMS\Gateway;

class sonoratecnologia extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://sonoratecnologia.ddns.net:1002/";
    public $tariff = "http://www.sonoratecnologia.com.br/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "Entre with country code like (27xxxxxxxxxx)";
        $this->help           = "For configuration gateway, please enter your username and password and enter the port gateway in `API/Key` field.";
        $this->has_key        = true;
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


        // Implode numbers
        $to = implode(",", $this->to);

        // Encode message
        $msg = urlencode($this->msg);

        // Set gateway port
        if ($this->has_key) {
            $port = "&port=" . $this->has_key;
        } else {
            $port = '';
        }

        $response = wp_remote_get($this->wsdl_link . "sendsms?username=" . $this->username . "&password=" . $this->password . "&phonenumber=" . $to . "&message=" . $msg . $port . "&report=1&timeout=0", [
            'timeout' => 30
        ]);

        $result = wp_remote_retrieve_body($response);

        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        if (strstr($result, 'success')) {

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $result);

            return true;
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result, 'error');

            return new \WP_Error('send-sms', $result);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        return true;
    }
}
