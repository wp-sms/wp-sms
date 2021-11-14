<?php

namespace WP_SMS\Gateway;

class easysendsms extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.easysendsms.com/sms/";
    public $tariff = "https://easysendsms.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "Eg: 61409317436, 61409317435, 61409317434 (Do not use + before the country code)";
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

        $type = 0;
        if (isset($this->options['send_unicode']) and $this->options['send_unicode'] and $this->isflash == true) {
            $type = 3;
        } else if (isset($this->options['send_unicode']) and $this->options['send_unicode'] and $this->isflash == false) {
            $type = 1;
        } else if ($this->isflash == true) {
            $type = 2;
        }

        $numbers = array();

        foreach ($this->to as $number) {
            $numbers[] = $this->clean_number($number);
        }

        $to  = implode(',', $numbers);
        $msg = urlencode($this->msg);

        $response = wp_remote_get($this->wsdl_link . "bulksms-api/bulksms-api?username=" . $this->username . "&password=" . $this->password . "&from=" . $this->from . "&to=" . $to . "&text=" . $msg . "&type=" . $type);

        // Check response error
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $result = $this->send_error_check($response['body']);

        if (!is_wp_error($result)) {
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
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result->get_error_message(), 'error');

            return new \WP_Error('send-sms', $result->get_error_message());
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('The Username/Password for this gateway is not set', 'wp-sms'));
        }

        return 1;

    }

    /**
     * Clean number
     *
     * @param $number
     *
     * @return bool|string
     */
    private function clean_number($number)
    {
        $number = str_replace('+', '', $number);
        $number = trim($number);

        return $number;
    }

    /**
     * @param $result
     *
     * @return string|\WP_Error
     */
    private function send_error_check($result)
    {

        switch ($result) {
            case strpos($result, 'OK') !== false:
                $error = '';
                break;
            case '1001':
                $error = 'Invalid URL. This means that one of the parameters was not provided or left blank.';
                break;
            case '1002':
                $error = 'Invalid username or password parameter.';
                break;
            case '1003':
                $error = 'Invalid type parameter.';
                break;
            case '1004':
                $error = 'Invalid message.';
                break;
            case '1005':
                $error = 'Invalid mobile number.';
                break;
            case '1006':
                $error = 'Invalid sender name.';
                break;
            case '1007':
                $error = 'Insufficient credit.';
                break;
            case '1008':
                $error = 'Internal error (do NOT re-submit the same message again).';
                break;
            case '1009':
                $error = 'Service not available (do NOT re-submit the same message again).';
                break;
            default:
                $error = sprintf('Unknow error: %s', $result);
                break;
        }

        if ($error) {
            return new \WP_Error('send-sms', $error);
        }

        return $result;
    }

}
