<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use WP_SMS\Exceptions\SmsGatewayException;

class smsservice extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://mihansmscenter.com/webservice/?wsdl";
    public $tariff = "http://smsservice.ir/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    private $soapAvailable = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";

        if (!class_exists('\SoapClient')) {
            $this->soapAvailable = false;
        } else {
            $this->soapAvailable = true;
        }
    }

    public function SendSMS()
    {
        if (!$this->soapAvailable) {
            $error = new \WP_Error(
                'soap-missing',
                __('PHP SoapClient is not available. SMS gateway is disabled.', 'wp-sms')
            );

            $this->log($this->from, $this->msg, $this->to, $error->get_error_message(), 'error');

            return $error;
        }

        if (!$this->username || !$this->password) {
            $error = new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));

            $this->log($this->from, $this->msg, $this->to, $error->get_error_message(), 'error');

            return $error;
        }

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
            $client = new \SoapClient($this->wsdl_link, [
                'encoding'   => 'UTF-8',
                'exceptions' => true,
                'trace'      => true,
            ]);

            $result = $client->__soapCall('multiSend', [[
                'username' => $this->username,
                'password' => $this->password,
                'to'       => $this->to,
                'from'     => $this->from,
                'message'  => $this->msg,
            ]]);

            $status = null;
            if (is_array($result) && isset($result['status'])) {
                $status = (int)$result['status'];
            } elseif (is_object($result) && isset($result->status)) {
                $status = (int)$result->status;
            }
        } catch (\SoapFault $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('soap-error', $e->getMessage());
        }

        if ($status === 0) {
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
        }
        // Log th result
        $this->log($this->from, $this->msg, $this->to, $result, 'error');

        return new \WP_Error('send-sms', $result);
    }

    public function GetCredit()
    {
        if (empty($this->soapAvailable)) {
            return new \WP_Error(
                'soap-missing',
                __('PHP SoapClient is not available.', 'wp-sms')
            );
        }

        // Check username and password
        if (!$this->username || !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        try {
            $client = new \SoapClient($this->wsdl_link, [
                'encoding'   => 'UTF-8',
                'exceptions' => true,
            ]);

            $result = $client->__soapCall('accountInfo', [[
                'username' => $this->username,
                'password' => $this->password,
            ]]);

            return (int)($result->balance ?? 0);

        } catch (\SoapFault $e) {
            return new \WP_Error('soap-error', $e->getMessage());
        }
    }
}