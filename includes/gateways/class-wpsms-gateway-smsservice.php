<?php

namespace WP_SMS\Gateway;

class smsservice extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://mihansmscenter.com/webservice/?wsdl";
    public $tariff = "http://smsservice.ir/";
    public $unitrial = false;
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

        $client = new \nusoap_client($this->wsdl_link, 'wsdl');
        $client->decodeUTF8(false);
        $result = $client->call('multiSend', array(
            'username' => $this->username,
            'password' => $this->password,
            'to'       => $this->to,
            'from'     => $this->from,
            'message'  => $this->msg
        ));

        if ($result['status'] === 0) {
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
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $client = new \nusoap_client($this->wsdl_link, 'wsdl');
        $client->decodeUTF8(false);
        $result = $client->call('accountInfo', array(
            'username' => $this->username,
            'password' => $this->password
        ));

        return (int)$result['balance'];
    }
}