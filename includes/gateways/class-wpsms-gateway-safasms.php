<?php

namespace WP_SMS\Gateway;

class safasms extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.safa-sms.com/api/";
    public $tariff = "https://www.safa-sms.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->has_key        = false;
        $this->validateNumber = "Separate each numbers with ',' ,Only support Arabic & English messages.";
    }

    public function SendSMS()
    {

        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         *
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         *
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         *
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

        // Clean numbers
        $numbers      = array();
        $country_code = isset($this->options['mobile_county_code']) ? $this->options['mobile_county_code'] : '';

        foreach ($this->to as $number) {
            $numbers[] = $this->clean_number($number, $country_code);
        }

        $numbers = implode(',', $numbers);

        $args = array(
            'body' => array(
                'username'     => $this->username,
                'password'     => $this->password,
                'message'      => $this->msg,
                'sender'       => $this->from,
                'numbers'      => $numbers,
                'return'       => 'json',
                'Rmduplicated' => 1,
            ),
        );

        $response = wp_remote_post($this->wsdl_link . "sendsms.php", $args);

        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {

            $result = $response['body'];

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

            return $result;
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username or !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $args = array(
            'body' => array(
                'username'      => $this->username,
                'password'      => $this->password,
                'return'        => 'json',
                'hangedBalance' => true,
            ),
        );

        $result = wp_remote_post($this->wsdl_link . "getbalance.php", $args);

        if (is_wp_error($result)) {
            return new \WP_Error('account-credit', $result->get_error_message());
        }

        $result = json_decode($result['body'], true);

        if ($result['Code'] == '117') {
            return $result['currentuserpoints'];
        }

        return $result['MessageIs'];
    }

    /**
     * @param $number
     *
     * @return bool|string
     */
    private function clean_number($number, $country_code)
    {
        //Clean Country Code from + or 00
        $country_code = str_replace('+', '', $country_code);

        if (substr($country_code, 0, 2) == "00") {
            $country_code = substr($country_code, 2, strlen($country_code));
        }

        //Remove +
        $number = str_replace('+', '', $number);

        //Remove 00 in the begining
        if (substr($number, 0, 2) == "00") {
            $number = substr($number, 2, strlen($number));
        }

        //Remove Repeated country code
        if (substr($number, 0, strlen($country_code) + 2) == $country_code . "00") {
            $number = substr($number, strlen($country_code) + 2);
        }

        if (substr($number, 0, strlen($country_code) * 2) == $country_code . $country_code) {
            $number = substr($number, strlen($country_code));
        }

        return $number;
    }

}