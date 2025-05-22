<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class matinsms extends Gateway
{
    const APIPATH = "http://api.kavenegar.com/v1/%s/%s/%s.json/";

    private $wsdl_link = "";
    public $tariff = "https://matinsms.ir";
    public $unitrial = false;
    public $unit;
    public $flash = false;
    public $isflash = false;

    private function get_path($method, $base = 'sms')
    {
        return sprintf(self::APIPATH, trim($this->has_key), $base, $method);
    }

    public function __construct()
    {
        parent::__construct();
        $this->has_key        = true;
        $this->bulk_send      = true;
        $this->help           = "Enter your MatinSMS API Key in the API Key field.<br>The correct formats for the recipient's phone number are as follows: 09121234567, 00989121234567, +989121234567, 9121234567";
        $this->validateNumber = "The correct formats for the recipient's phone number are as follows: 09121234567, 00989121234567, +989121234567, 9121234567";
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
            if (empty($this->has_key)) {
                throw new Exception(__('The API Key for this gateway is not set', 'wp-sms-pro'));
            }

            $path = $this->get_path('send');

            $params = [
                'receptor' => implode(",", $this->to),
                'sender'   => $this->from,
                'message'  => urlencode($this->msg),
            ];

            $response = $this->request('GET', $path, $params);

            if ($response->return->status != 200) {
                throw new Exception($response->return->message);
            }

            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
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
            if (empty($this->has_key)) {
                return new WP_Error('account-credit', __('The API Key for this gateway is not set', 'wp-sms-pro'));
            }

            $path = $this->get_path('info', 'account');

            $response = $this->request('GET', $path);

            if ($response->return->status != 200) {
                throw new Exception($response->return->message);
            }

            return $response->entries->remaincredit;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }
}
