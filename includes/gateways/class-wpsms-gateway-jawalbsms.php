<?php

namespace WP_SMS\Gateway;

class jawalbsms extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.jawalbsms.ws/api.php/";
    public $tariff = "https://www.jawalbsms.ws/";
    public $unitrial = false;
    public $unit;
    public $flash = "disabled";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "966XXXXXXXXX";
        $this->help = '<a href="https://jawalbsms.ws">إرسال رسائل نصية جماعية  باللغتين العربية والإنجليزية إلى المملكة العربية السعودية ، دبي ، منطقة الخليج ، تركيا ، مصر ، إفريقيا ، الولايات المتحدة الأمريكية ، المملكة المتحدة ، كندا ، الهند ، 150 دولة أخرى (Send Arabic/English  Bulk  Sms to Saudi Arabia, Dubai, Gulf Region, Turkey,  Egypt,  Africa, USA, UK, Canada, Europe, India, other150 countries)</a> ';
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

        $country_code = isset($this->options['mobile_county_code']) ? $this->options['mobile_county_code'] : '';

        $to = [];
        foreach ($this->to as $value) {
            $to[] = $this->clean_number($value, $country_code);
        }

        $to     = implode(',', $to);
        $msg    = urlencode($this->msg);
        $sender = urlencode($this->from);

        $response = wp_remote_get("{$this->wsdl_link}sendsms?user={$this->username}&pass={$this->password}&to={$to}&message={$msg}&sender={$sender}");

        // Check response error
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $result = wp_remote_retrieve_body($response);

        if (strpos($result, 'Success') !== false) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);

            /**
             * Run hook after send sms.
             *
             * @since 2.4
             */
            do_action('wp_sms_send', $result);

            return $result;
        } else {
            $this->log($this->from, $this->msg, $this->to, $this->getErrorByCode($result), 'error');

            return new \WP_Error('send-sms', $this->getErrorByCode($result));
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $response = wp_remote_get("{$this->wsdl_link}chk_balance?user={$this->username}&pass={$this->password}");

        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $result = wp_remote_retrieve_body($response);

        if (intval($result) < 1) {
            return new \WP_Error('account-credit', $this->getErrorByCode($result));
        }

        return $result;
    }

    private function clean_number($number, $country_code)
    {
        //Clean Country Code from + or 00
        $country_code = str_replace('+', '', $country_code);

        if (substr($country_code, 0, 2) == "00") {
            $country_code = substr($country_code, 2, strlen($country_code));
        }

        //Remove +
        $number = str_replace('+', '', $number);

        if (substr($number, 0, strlen($country_code) * 2) == $country_code . $country_code) {
            $number = substr($number, strlen($country_code) * 2);
        } else {
            $number = substr($number, strlen($country_code));
        }

        //Remove 00 in the begining
        if (substr($number, 0, 2) == "00") {
            $number = substr($number, 2, strlen($number));
        }

        //Remove 00 in the begining
        if (substr($number, 0, 1) == "0") {
            $number = substr($number, 1, strlen($number));
        }

        return $country_code . $number;
    }

    private function getErrorByCode($code)
    {
        // Remove non-ascii characters from string
        $code = preg_replace('/[[:^print:]]/', '', $code);

        $errors = [
            '-100' => 'Missing parameters (not exist or empty) Username +password',
            '-110' => 'Account not exist (wrong username or password)',
            '-111' => 'The account not activated',
            '-112' => 'Blocked account',
            '-114' => 'The service not available for now',
            '-120' => 'No destination addresses, or all destinations are incorrect',
            '-116' => 'Invalid sender name',
            '-130' => 'Error in MsgID (used with cancel schedule message)',
        ];

        if (isset($errors[$code])) {
            return $errors[$code];
        }

        return $code;
    }
}
