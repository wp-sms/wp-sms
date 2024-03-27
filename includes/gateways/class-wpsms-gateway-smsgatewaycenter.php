<?php

namespace WP_SMS\Gateway;

class smsgatewaycenter extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.smsgateway.center/SMSApi/rest/";
    public $tariff = "https://www.smsgateway.center/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "91xxxxxxxxxx";
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

        $result = $this->request('GET', $this->wsdl_link . "send?userId=" . $this->username . "&password=" . $this->password . "&sendMethod=simpleMsg&msgType=dynamic&mobile=" . implode(',', $this->to) . "&senderId=" . $this->from . "&msg=" . $msg . "&format=json", [], [], false);

        if ($result->status == 'error') {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result, 'error');
            return false;
        }

        if ($result->status == 'success') {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', lcfirst($result->status) . ' | ' . $result->transactionId);

            return lcfirst($result->status) . ' | ' . $result->transactionId;
        }
        // Log the result
        $this->log($this->from, $this->msg, $this->to, $result, 'error');

        return new \WP_Error('send-sms', lcfirst($result->status) . ' | ' . $result->transactionId);
    }

    /**
     * Get Balance
     * @return \WP_Error|boolean
     */
    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }
        $result = $this->request('GET', $this->wsdl_link . "balanceValidityCheck?userId=" . $this->username . "&password=" . $this->password . "&format=json", [], [], false);
        if ($result->status !== 'success') {
            return new \WP_Error('account-credit', "$result->status | $result->errorCode | $result->reason");
        }
        return $result->smsBalance;
    }
}
