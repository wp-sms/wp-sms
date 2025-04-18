<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class _1s2u extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.1s2u.io";
    public $tariff = "https://1s2u.com";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->has_key        = false;
        $this->validateNumber = "The phone number must contain only digits together with the country code. It should not contain any other symbols such as (+) sign.  Instead  of  plus  sign,  please  put  (00)" . PHP_EOL . "e.g seperate numbers with comma: 12345678900, 11222338844";
        $this->help           = "";
        $this->gatewayFields  = [
            'username' => [
                'id'   => 'gateway_username',
                'name' => 'Registered Username',
                'desc' => 'Enter your username.',
            ],
            'password' => [
                'id'   => 'gateway_password',
                'name' => 'Password',
                'desc' => 'Enter your password.',
            ],
            'from'     => [
                'id'           => 'gateway_sender_id',
                'name'         => 'Sender Number',
                'place_holder' => 'e.g., +1 555 123 4567',
                'desc'         => 'This is the number or sender ID displayed on recipients’ devices.
It might be a phone number (e.g., +1 555 123 4567) or an alphanumeric ID if supported by your gateway.',
            ],
        ];
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

        try {

            $mt = 0;
            if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
                $mt = 1;
            }

            $fl = 0;
            if ($this->isflash) {
                $fl = 1;
            }

            $numbers = array();

            foreach ($this->to as $number) {
                $numbers[] = $this->clean_number($number);
            }

            $arguments = array(
                'username' => $this->username,
                'password' => $this->password,
                'mno'      => implode(',', $numbers),
                'Sid'      => $this->from,
                'msg'      => urlencode($this->msg),
                'mt'       => $mt,
                'fl'       => $fl
            );

            $response = $this->request('POST', "{$this->wsdl_link}/bulksms", $arguments);

            if (isset($response) && !strpos($response, 'OK')) {
                throw new Exception($this->getErrorMessage($response));
            }

            //log the result
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

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }

    }

    /**
     * @return string | WP_Error
     * @throws Exception
     */
    public function GetCredit()
    {

        try {
            // Check username and password
            if (!$this->username || !$this->password) {
                throw new Exception(esc_html__('Username and password are required.', 'wp-sms'));
            }

            $arguments = [
                'USER' => $this->username,
                'PASS' => $this->password
            ];

            $response = $this->request('POST', "{$this->wsdl_link}/checkbalance", $arguments);

            if (isset($response) && $response == '00') {
                throw new Exception($this->getErrorMessage($response));
            }

            return $response;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }

    }

    /**
     * Clean number
     *
     * @param $number
     *
     * @return string
     */
    private function clean_number($number)
    {
        $number = str_replace('+', '00', $number);

        return trim($number);
    }

    /**
     * Get error message from the request error code
     *
     * @param $errorCode
     * @return string|null
     */
    private function getErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case $errorCode === '0000':
                $message = esc_html__('Service Not Available or Down Temporary', 'wp-sms');
                break;

            case $errorCode === '00':
                $message = esc_html__('Invalid username/password.', 'wp-sms');
                break;

            case $errorCode === '0005':
                $message = esc_html__('Invalid server', 'wp-sms');
                break;

            case $errorCode === '0010':
                $message = esc_html__('Username not provided.', 'wp-sms');
                break;

            case $errorCode === '0011':
                $message = esc_html__('Password not provided.', 'wp-sms');
                break;

            case $errorCode === '0':
            case $errorCode === '0020':
                $message = esc_html__('Insufficient Credits', 'wp-sms');
                break;

            case $errorCode === '0030':
                $message = esc_html__('Invalid Sender ID', 'wp-sms');
                break;

            case $errorCode === '0040':
                $message = esc_html__('Mobile number not provided.', 'wp-sms');
                break;

            case $errorCode === '0041':
                $message = esc_html__('Invalid mobile number', 'wp-sms');
                break;

            case $errorCode === '0042':
                $message = esc_html__('Network not supported.', 'wp-sms');
                break;

            case $errorCode === '0050':
                $message = esc_html__('Invalid message.', 'wp-sms');
                break;

            case $errorCode === '0060':
                $message = esc_html__('Invalid quantity specified.', 'wp-sms');
                break;

            case $errorCode === '0066':
                $message = esc_html__('Network not supported', 'wp-sms');
                break;

            default:
                $message = esc_html__("Something's wrong. Please contact the SMS gateway provider support team.", 'wp-sms');
        }

        return $message;
    }

}
