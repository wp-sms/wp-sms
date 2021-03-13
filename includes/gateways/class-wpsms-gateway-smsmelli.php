<?php

namespace WP_SMS\Gateway;

class smsmelli extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://smsmelli.com/class/sms/webservice3/server.php?wsdl";
    private $client = null;
    public $tariff = "http://smsmelli.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";

        if (!class_exists('nusoap_client')) {
            include_once WP_SMS_DIR . 'includes/libraries/nusoap.class.php';
        }

        $this->client = new \nusoap_client($this->wsdl_link, array('trace' => true));

        $this->client->soap_defencoding = 'UTF-8';
        $this->client->decode_utf8      = true;
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

        $result = $this->client->call("SendSMS", array(
            'user'           => $this->username,
            'pass'           => $this->password,
            'fromNum'        => $this->from,
            'toNum'          => $this->to,
            'messageContent' => $this->msg,
            'messageType'    => 'normal'
        ));

        if ($result) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);
            do_action('wp_sms_send', $result);

            return $result;
        }
        // Log the result
        $this->log($this->from, $this->msg, $this->to, $result, 'error');

        return new \WP_Error('send-sms', $result);
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $result = $this->client->call("GetCredit", array('user' => $this->username, 'pass' => $this->password));

        return $result;
    }
}
