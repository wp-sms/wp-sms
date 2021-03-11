<?php

namespace WP_SMS\Gateway;

class bearsms extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://app.bearsms.com/index.php?app=ws";
    public $tariff = "http://www.bearsms.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "97xxxxxxxxxxx";
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

        $to  = implode(',', $this->to);
        $msg = urlencode($this->msg);

        $result     = file_get_contents($this->wsdl_link . '&u=' . $this->username . '&h=' . $this->password . '&op=pv&to=' . $to . '&msg=' . $msg);
        $result_arr = json_decode($result);

        if ($result_arr->data[0]->status == 'ERR') {
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
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $result     = file_get_contents($this->wsdl_link . '&u=' . $this->username . '&h=' . $this->password . '&op=cr');
        $result_arr = json_decode($result);

        if ($result_arr->status == 'ERR') {
            return new \WP_Error('account-credit', $result);
        }

        return $result_arr->credit;
    }
}