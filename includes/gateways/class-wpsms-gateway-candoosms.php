<?php

namespace WP_SMS\Gateway;

class candoosms extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://my.candoosms.com/services/?wsdl";
    public $tariff = "http://candoosms.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
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

        $client                   = new \nusoap_client($this->wsdl_link, true);
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8      = false;

        $result = $client->call('Send', array(
            'username'  => $this->username,
            'password'  => $this->password,
            'srcNumber' => $this->from,
            'body'      => $this->msg,
            'destNo'    => $this->to,
            'flash'     => '0'
        ));

        if ($client->fault) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result, 'error');

            return new \WP_Error('send-sms', $result);
        } else {
            if ($client->getError()) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $client->getError(), 'error');

                return new \WP_Error('send-sms', $client->getError());
            } else {
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
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $client = new \nusoap_client($this->wsdl_link, true);

        if ($client->getError()) {
            return new \WP_Error('account-credit', $client->getError());
        }

        $result = $client->call('Balance', array('username' => $this->username, 'password' => $this->password));

        if ($result) {
            return $result;
        } else {
            return new \WP_Error('account-credit', $result);
        }
    }
}