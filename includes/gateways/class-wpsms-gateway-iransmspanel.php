<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class iransmspanel extends \WP_SMS\Gateway
{
    /**
     * Host
     *
     * @var    string
     */
    private $host = "2972.ir";

    /**
     * URI
     *
     * @var    string
     */
    private $uri = '/api';

    private $wsdl_link = "http://www.2972.ir/wsdl?XML";

    public $tariff = "http://iransmspanel.ir/?TheAction=viewpage&title=smscharge";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $port = 0;
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";
    }

    /**
     * Send SMS via WordPress HTTP API.
     *
     * @param $username
     * @param $password
     * @param $number
     * @param $recipient
     * @param $port
     * @param $message
     * @param $flash
     *
     * @return string|false
     */
    private function sendRequest($username, $password, $number, $recipient, $port, $message, $flash)
    {
        $response = wp_remote_post('http://www.2972.ir/api', [
            'body' => [
                'username'  => $username,
                'password'  => $password,
                'number'    => $number,
                'recipient' => $recipient,
                'port'      => $port,
                'message'   => $message,
                'flash'     => $flash
            ]
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * This function is used to send SMS via http://www.2972.ir
     * @return bool|\WP_Error
     * @internal param Username $string
     * @internal param Password $string
     * @internal param Number $string (From - Example: 100002972)
     * @internal param Recipient $string Number
     * @internal param Port $integer Number
     * @internal param Message $string
     * @internal param Is $bool Flash SMS?
     *
     */
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

        $result = $this->sendRequest($this->username, $this->password, $this->from, implode(',', $this->to), $this->port, $this->msg, $this->isflash);

        if (!$result) {
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
        }
        // Log the result
        $this->log($this->from, $this->msg, $this->to, $result, 'error');

        return new \WP_Error('send-sms', $result);
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', esc_html__('Username and Password are required.', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return new \WP_Error('required-class', esc_html__('Class SoapClient not found. please enable php_soap in your php.', 'wp-sms'));
        }

        try {
            $client = new \SoapClient($this->wsdl_link);
        } catch (\Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }

        $result = $client->Authentication($this->username, $this->password);

        if ($result == '1') {
            return $client->GetCredit();
        } else {
            return new \WP_Error('account-credit', $result);
        }
    }
}
