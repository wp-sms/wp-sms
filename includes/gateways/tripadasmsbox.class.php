<?php

class tripadasmsbox extends WP_SMS
{
    private $wsdl_link = "http://tripadasmsbox.com/api/";
    public $tariff = "http://tripadasmsbox.com";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "";
        $this->has_key = true;
        $this->help = 'Enter your AUTH Key in the API key field and to define custom route you can set this value in Username field.';
    }

    public function SendSMS()
    {
        // Check gateway credit
        if (is_wp_error($this->GetCredit())) {
            return new WP_Error('account-credit', __('Your account does not credit for sending sms.', 'wp-sms'));
        }
        /**
         * Modify sender number
         *
         * @since 3.4
         *
         * @param string $this ->from sender number.
         */
        $this->from = apply_filters('wp_sms_from', $this->from);
        /**
         * Modify Receiver number
         *
         * @since 3.4
         *
         * @param array $this ->to receiver number
         */
        $this->to = apply_filters('wp_sms_to', $this->to);
        /**
         * Modify text message
         *
         * @since 3.4
         *
         * @param string $this ->msg text message.
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);
        $to = implode($this->to, ",");

        if ($this->username) {
            $route = $this->username;
        } else {
            $route = 'default';
        }

        $response = wp_remote_get($this->wsdl_link . "sendapi.php?auth_key=" . $this->has_key . "&mobiles=" . $to . "&message=" . urlencode($this->msg) . "&sender=" . $this->from . "&route=" . $route);

        // Check response error
        if (is_wp_error($response)) {
            return new WP_Error('send-sms', $response->get_error_message());
        }

        // Ger response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Check response code
        if ($response_code == '200') {
            if (strpos($response['body'], 'Error') !== false) {
                return new WP_Error('send-sms', $response['body']);
            }

            $this->InsertToDB($this->from, $this->msg, $this->to);

            /**
             * Run hook after send sms.
             *
             * @since 2.4
             *
             * @param string $response result output.
             */
            do_action('wp_sms_send', $response);

            return $response;

        } else {
            return new WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->has_key) {
            return new WP_Error('account-credit', __('Username/Password does not set for this gateway', 'wp-sms'));
        }

        if ($this->username) {
            $route = $this->username;
        } else {
            $route = 'default';
        }

        $response = wp_remote_get($this->wsdl_link . "balance.php?auth_key=" . $this->has_key . "&type=" . $route);

        if (!is_wp_error($response)) {
            $data = json_decode($response['body']);

            if (isset($data->error)) {
                return new WP_Error('account-credit', $data->error);
            }

            return $data->balence;
        } else {
            return new WP_Error('account-credit', $response->get_error_message());
        }
    }
}
