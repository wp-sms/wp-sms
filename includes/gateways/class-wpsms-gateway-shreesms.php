<?php

namespace WP_SMS\Gateway;

class shreesms extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://ip.shreesms.net/";
    public $tariff = "http://www.shreesms.net";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
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

        $msg = urlencode($this->msg);

        foreach ($this->to as $number) {
            $result = file_get_contents("{$this->wsdl_link}smsserver/SMS10N.aspx?Userid={$this->username}&UserPassword={$this->password}&PhoneNumber={$number}&Text={$msg}&GSM={$this->from}");
        }

        if ($result = 'Ok') {
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
        // Log the result
        $this->log($this->from, $this->msg, $this->to, $result, 'error');

        return new \WP_Error('send-sms', $result);
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $result = file_get_contents("{$this->wsdl_link}SMSServer/SMSCnt.asp?ID={$this->username}&pw={$this->password}");

        if (preg_replace('/[^0-9]/', '', $result)) {
            return $result;
        } else {
            return new \WP_Error('account-credit', $result);
        }
    }
}