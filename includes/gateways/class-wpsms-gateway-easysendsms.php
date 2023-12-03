<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class easysendsms extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.easysendsms.app";
    public $tariff = "https://easysendsms.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
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

        $numbers = array();

        foreach ($this->to as $number) {
            $numbers[] = $this->clean_number($number);
        }

        try {

            $arguments = [
                'username' => $this->username,
                'password' => $this->password,
                'from'     => $this->from,
                'to'       => implode(',', $numbers),
                'text'     => urlencode($this->msg),
                'type'     => 0,
            ];

            if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
                $arguments['type'] = 1;
            }

            $parameters = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body'    => $arguments
            ];

            // Get Send SMS Response
            $response = $this->request('POST', "{$this->wsdl_link}/bulksms", [], $parameters);
            $error    = $this->getErrorMessageByCode($response);

            if ($error) {
                throw new Exception($error);
            }

            // Log the result
            $this->log($this->from, $this->msg, $numbers, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
             *
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

            return $response;

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $numbers, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        try {
            if (!$this->username && !$this->password) {
                throw new Exception('Username and Password are required.');
            }

            $args = array(
                'username' => $this->username,
                'password' => $this->password,
            );

            // Get Credit Response
            $response = $this->request('GET', "{$this->wsdl_link}/balance", $args);
            $error    = $this->getErrorMessageByCode($response);

            if ($error) {
                throw new Exception($error);
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
     * @return bool|string
     */
    private function clean_number($number)
    {
        $number = str_replace('+', '', $number);
        $number = trim($number);

        return $number;
    }

    private function getErrorMessageByCode($code)
    {
        switch ($code) {
            case '1001':
                return 'Invalid URL. This means that one of the parameters was not provided or left blank.';
                break;
            case '1002':
                return 'Invalid username or password parameter.';
                break;
            case '1003':
                return 'Invalid type parameter.';
                break;
            case '1004':
                return 'Invalid message.';
                break;
            case '1005':
                return 'Invalid mobile number.';
                break;
            case '1006':
                return 'Invalid sender name.';
                break;
            case '1007':
                return 'Insufficient credit.';
                break;
            case '1008':
                return 'Internal error (do NOT re-submit the same message again).';
                break;
            case '1009':
                return 'Service not available (do NOT re-submit the same message again).';
                break;
        }

        return false;
    }

}
