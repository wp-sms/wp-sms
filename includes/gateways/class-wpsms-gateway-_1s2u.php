<?php

namespace WP_SMS\Gateway;

class _1s2u extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.1s2u.io/";
    public $tariff = "https://1s2u.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "The phone number must contain only digits together with the country code. It should not contain any other symbols such as (+) sign.  Instead  of  plus  sign,  please  put  (00)" . PHP_EOL . "e.g seperate numbers with comma: 12345678900, 11222338844";
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

        $mt = 0;
        if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
            $mt = 1;
        }

        $fl = 0;
        if ($this->isflash == true) {
            $fl = 1;
        }

        $numbers = array();

        foreach ($this->to as $number) {
            $numbers[] = $this->clean_number($number);
        }

        $to  = implode(',', $numbers);
        $msg = urlencode($this->msg);

        $response = wp_remote_get($this->wsdl_link . "bulksms?username=" . $this->username . "&password=" . $this->password . "&mno=" . $to . "&id=" . $this->from . "&msg=" . $msg . "&mt=" . $mt . "&fl=" . $fl);

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

        $response = wp_remote_get($this->wsdl_link . "checkbalance?user=" . $this->username . "&pass=" . $this->password);

        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $result = json_decode($response['body']);

        if ($result and is_int($result) and $result != 00) {
            return $result;
        } else {
            return new \WP_Error('account-credit', 'Invalid username or password');
        }

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
        $number = str_replace('+', '00', $number);
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
            case '0000':
                $error = 'Service Not Available or Down Temporary.';
                break;
            case '0005':
                $error = 'Invalid server.';
                break;
            case '0010':
                $error = 'Username not provided.';
                break;
            case '0011':
                $error = 'Password not provided.';
                break;
            case '00':
                $error = 'Invalid username/password.';
                break;
            case '0020 / 0':
                $error = 'Insufficient Credits.';
                break;
            case '0020':
                $error = 'Insufficient Credits.';
                break;
            case '0':
                $error = 'Insufficient Credits.';
                break;
            case '0030':
                $error = 'Invalid Sender ID.';
                break;
            case '0040':
                $error = 'Mobile number not provided.';
                break;
            case '0041':
                $error = 'Invalid mobile number.';
                break;
            case '0042':
                $error = 'Network not supported.';
                break;
            case '0050':
                $error = 'Invalid message.';
                break;
            case '0060':
                $error = 'Invalid quantity specified.';
                break;
            case '0066':
                $error = 'Network not supported.';
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
