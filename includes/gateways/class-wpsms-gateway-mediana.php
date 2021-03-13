<?php

namespace WP_SMS\Gateway;

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

        if (!class_exists('SoapClient')) {
            return new \WP_Error('required-class', __('Class SoapClient not found. please enable php_soap in your php.', 'wp-sms'));
        }

        $this->client = new \SoapClient($this->wsdl_link, ['exceptions' => false, 'encoding' => 'UTF-8']);
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

        $result = $this->client->__soapCall("SendSMS", $params);

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
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $params = [
            'Username' => $this->username,
            'Password' => $this->password
        ];

        $result = $this->client->__soapCall("GetCredit", $params);

        return $result;
    }
}