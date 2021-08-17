<?php

namespace WP_SMS\Gateway;

class _textplode extends \WP_SMS\Gateway
{
    private $wsdl_link = "";
    public $tariff = "https://www.textplode.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "440000000000,440000000001";

        // Enable api key
        $this->has_key = true;

        // Include library
        include('libraries/textplode/textplode.class.php');
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

        // Get the credit.
        $credit = $this->GetCredit();

        // Check gateway credit
        if (is_wp_error($credit)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return $credit;
        }

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         * @since 3.4
         *
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        // Init class
        $textplode = new \Textplode($this->has_key);

        // Add recipient
        foreach ($this->to as $to) {
            $textplode->messages->add_recipient($to, array());
        }

        // Set From Name
        $textplode->messages->set_from($this->from);

        // Set Message
        $textplode->messages->set_message($this->msg);

        // Send sms
        $result = $textplode->messages->send();

        // Check result
        if (!$result) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result, 'error');

            return new \WP_Error('send-sms', $result);
        }

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

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        // Init class
        $textplode = new \Textplode($this->has_key);

        // Get credit
        $credits = $textplode->account->get_credits();

        return $credits;
    }
}