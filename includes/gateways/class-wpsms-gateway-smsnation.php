<?php

namespace WP_SMS\Gateway;

class smsnation extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://sms-api.smsnation.co/smsp-in";
    public $tariff = "http://smsnation.co.rw/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "The Destination number must not be longer than 20 characters and it must be written in international format ( without the leading + or 00)";
        $this->help           = "Please enter Originator in Sender Number field";
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
        $to  = implode(",",$this->to);
        $msg = urlencode($this->msg);

        $args = array(
            'originator'       => $this->from,
            'text'             => $msg,
            'request_delivery' => 'true',
            'mobile_number'    => $to,
        );

        $response = wp_remote_get(add_query_arg($args, 'http://' . $this->username . ':' . $this->password . '@' . $this->wsdl_link));

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
            $this->log($this->from, $this->msg, $this->to, $response->result_info->description, 'error');

            return new \WP_Error('account-credit', $response->result_info->description);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        // Check Connect To Service
        $response = wp_remote_get(add_query_arg('get_balance', 'true', 'http://' . $this->username . ':' . $this->password . '@' . $this->wsdl_link));
        if (is_wp_error($response)) {
            return new \WP_Error('send-sms', $response->get_error_message());
        }

        //Check Username and Password
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code != 200) {
            return new \WP_Error('account-credit', __('Server API Unavailable', 'wp-sms'));
        }


        return $response['body'];
    }
}
