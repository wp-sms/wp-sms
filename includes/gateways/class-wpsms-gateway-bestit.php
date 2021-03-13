<?php

namespace WP_SMS\Gateway;

class bestit extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://panelsms.bestit.co/WsSms.asmx?wsdl";
    public $tariff = "http://panelsms.bestit.co/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";
        $this->has_key        = true;

        @ini_set("soap.wsdl_cache_enabled", "0");
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

        $client = new \SoapClient($this->wsdl_link);

        $result = $client->sendsms(
            array(
                'username' => $this->username,
                'password' => $this->password,
                'to'       => implode(',', $this->to),
                'text'     => $this->msg,
                'from'     => $this->from,
                'api'      => $this->has_key,
            )
        );

        if (
            $result->sendsmsResult->long == 1000 or
            $result->sendsmsResult->long == 1001 or
            $result->sendsmsResult->long == 1002 or
            $result->sendsmsResult->long == 1003 or
            $result->sendsmsResult->long == 1004 or
            $result->sendsmsResult->long == 1005 or
            $result->sendsmsResult->long == 1006 or
            $result->sendsmsResult->long == 1007 or
            $result->sendsmsResult->long == 1008 or
            $result->sendsmsResult->long == 1009 or
            $result->sendsmsResult->long == 1010
        ) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $this->GetCredit()->get_error_message(), 'error');

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

        if (!class_exists('SoapClient')) {
            return new \WP_Error('required-class', __('Class SoapClient not found. please enable php_soap in your php.', 'wp-sms'));
        }

        try {
            $client = new \SoapClient($this->wsdl_link);
        } catch (\Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }

        $result = $client->Credites(array('username' => $this->username, 'password' => $this->password));

        return $result->CreditesResult;
    }
}