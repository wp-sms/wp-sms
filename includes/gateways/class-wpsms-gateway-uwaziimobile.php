<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class uwaziimobile extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api2.uwaziimobile.com";
    public $tariff = "https://www.uwaziimobile.com";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send = true;
        $this->has_key = true;
        $this->validateNumber = "Destination addresses must be in international format (Example: 254722123456).";
        $this->help = "Enter your Gateway Token and Sender ID. You can avail them from your control panel.";
        $this->gatewayFields = [
            'has_key' => [
                'id' => 'gateway_key',
                'name' => 'Gateway Token',
                'desc' => 'Enter your Gateway Token. The gateway token can be generated through your control panel.',
            ],
            'from' => [
                'id' => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'Enter the Sender ID. ',
            ],
        ];
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

        try {

            // Get the credit.
            $credit = $this->GetCredit();

            // Check gateway credit
            if (is_wp_error($credit)) {
                throw new Exception($credit->get_error_message());
            }

            $country_code  = $this->options['mobile_county_code'] ?? '';
            $mobileNumbers = array_map(function ($item) use ($country_code) {
                return $this->clean_number($item, $country_code);
            }, $this->to);

            $response = $this->request('GET', "$this->wsdl_link/send", [
                [
                    'token' => $this->has_key,
                    'phone' => implode($mobileNumbers),
                    'senderID' => $this->from,
                    'text' => $this->msg,
                ],
            ], []);

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

    public function GetCredit()
    {

        try {

            // Check username and password
            if (!$this->has_key) {
                throw new Exception(__('Gateway Token is not entered.', 'wp-sms'));
            }

            return 1;

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            return new WP_Error('account-credit', $error_message);
        }

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

        //Remove 00 in the beginning
        if (substr($number, 0, 2) == "00") {
            $number = substr($number, 2, strlen($number));
        }

        //Remove 00 in the beginning
        if (substr($number, 0, 1) == "0") {
            $number = substr($number, 1, strlen($number));
        }

        return $country_code . $number;
    }
}