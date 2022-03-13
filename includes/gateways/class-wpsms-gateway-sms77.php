<?php

namespace WP_SMS\Gateway;

class sms77 extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://gateway.sms77.de/";
    public $tariff = "http://www.sms77.de";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->has_key        = true;
        $this->help           = 'For API Key find it in your login under Settings > HTTP Api';
        $this->validateNumber = "0049171999999999 or 0171999999999 or 49171999999999";
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

        $result = @file_get_contents($this->wsdl_link . '?p=' . urlencode($this->has_key) . '&text=' . urlencode($this->msg) . '&to=' . implode(",",$this->to) . '&type=quality&from=' . urlencode($this->from));

        if ($error = $this->get_error($result)) {
            return new \WP_Error('send-sms', $error);
        }

        // Log the result
        $this->log($this->from, $this->msg, $this->to, $result);

        /**
         * Run hook after send sms.
         *
         * @param string $result result output.
         *
         * @since 2.4
         *
         */
        do_action('wp_sms_send', $result);

        return $result;

        // Log the result
        $this->log($this->from, $this->msg, $this->to, $result, 'error');

        return new \WP_Error('send-sms', $result);
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . 'balance.php?p=' . urlencode($this->has_key));

        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        if ($error = $this->get_error($response['body'])) {
            return new \WP_Error('account-credit', $error);
        }

        return $response['body'];
    }

    private function get_error($code)
    {
        switch ($code) {
            case 101:
                $error = 'Transmission to at least one recipient failed';
                break;
            case 201:
                $error = 'Sender invalid. A maximum of 11 alphanumeric or 16 numeric characters are allowed.';
                break;
            case 202:
                $error = 'Recipient number invalid';
                break;
            case 300:
                $error = 'Variable p is not specified';
                break;
            case 301:
                $error = 'Variable to not set';
                break;
            case 304:
                $error = 'Variable type not set';
                break;
            case 305:
                $error = 'Variable text not set';
                break;
            case 400:
                $error = 'type invalid. See allowed values ​​above.';
                break;
            case 401:
                $error = 'Variable text is too long';
                break;
            case 402:
                $error = 'Reload Lock – this SMS has already been sent within the last 180 seconds';
                break;
            case 403:
                $error = 'Max. limit per day reached for this number';
                break;
            case 500:
                $error = 'Too little credit available';
                break;
            case 600:
                $error = 'Carrier delivery failed';
                break;
            case 700:
                $error = 'Unknown error';
                break;
            case 900:
                $error = 'Authentication failed. Please check user and api key';
                break;
            case 902:
                $error = 'http API disabled for this account';
                break;
            case 903:
                $error = 'Server IP is wrong';
                break;
            case 11:
                $error = 'SMS carrier temporarily not available';
                break;

            default:
                $error = false;
        }

        return $error;
    }
}