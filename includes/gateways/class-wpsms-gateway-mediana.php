<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class mediana extends \WP_SMS\Gateway
{

    public $tariff = "http://mediana.ir/";
    public $unitrial = true;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    private $wsdl_link = "https://panel.mediana.ir/class/sms/wssimple/server.php?wsdl";
    private $client = null;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";

    }

    /**
     * Connects to Mediana only when a request is made, never on page load.
     *
     * The constructor runs on every page, so loading the WSDL there turned a
     * Mediana outage (HTTP 502) into a fatal error across the whole site.
     * Exceptions stay on: with 'exceptions' => false PHP reports a failed WSDL
     * download as an uncatchable fatal error instead of a SoapFault.
     *
     * @return \SoapClient|\WP_Error
     */
    private function getClient()
    {
        if ($this->client) {
            return $this->client;
        }

        if (!class_exists('SoapClient')) {
            return new \WP_Error('required-class', esc_html__('Class SoapClient not found. please enable php_soap in your php.', 'wp-sms'));
        }

        try {
            $this->client = new \SoapClient($this->wsdl_link, ['encoding' => 'UTF-8', 'connection_timeout' => 10]);
        } catch (\Throwable $e) {
            /* translators: %s: error message */
            return new \WP_Error('gateway-unreachable', sprintf(esc_html__('Mediana could not be reached: %s', 'wp-sms'), $e->getMessage()));
        }

        return $this->client;
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

        $params = [
            'Username'         => $this->username,
            'Password'         => $this->password,
            'SenderNumber'     => $this->from,
            'RecipientNumbers' => $this->to,
            'Message'          => $this->msg,
            'Type'             => 'normal'
        ];

        $client = $this->getClient();

        if (is_wp_error($client)) {
            $this->log($this->from, $this->msg, $this->to, $client->get_error_message(), 'error');

            return $client;
        }

        try {
            $result = $client->__soapCall("SendSMS", $params);
        } catch (\Throwable $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('send-sms', $e->getMessage());
        }

        if ($result) {
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
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', esc_html__('API username or API password is not entered.', 'wp-sms'));
        }

        $params = [
            'Username' => $this->username,
            'Password' => $this->password
        ];

        $client = $this->getClient();

        if (is_wp_error($client)) {
            return $client;
        }

        try {
            $result = $client->__soapCall("GetCredit", $params);
        } catch (\Throwable $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }

        return $result;
    }
}