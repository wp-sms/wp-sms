<?php

namespace WP_SMS\Gateway;

class melipayamak extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://api.payamak-panel.com/post/Send.asmx?wsdl";
    public $tariff = "http://melipayamak.ir/";
    public $unitrial = true;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";

        @ini_set("soap.wsdl_cache_enabled", "0");
    }

    public function SendSMS()
    {
        // Check gateway credit
        if (is_wp_error($this->GetCredit())) {
            return new \WP_Error('account-credit', esc_html__('Your account does not credit for sending sms.', 'wp-sms'));
        }

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
        $textarray_wp = explode("##", $this->msg);
        $key          = array_pop($textarray_wp);
        if (trim($key) == "shared") {
            try {
                $text_wp1      = implode(" ", $textarray_wp);
                $textarray_wp2 = explode("-", $text_wp1);
                $bodyid        = array_shift($textarray_wp2);
                $client        = new \SoapClient($this->wsdl_link);
                for ($i = 0; $i < count($this->to); $i++) {
                    $data   = [
                        "username" => $this->username,
                        "password" => $this->password,
                        "text"     => $textarray_wp2[0],
                        "to"       => $this->to[$i],
                        "bodyId"   => $bodyid
                    ];
                    $result = $client->SendByBaseNumber2($data)->SendByBaseNumber2Result;
                    if ($result > 1000) {
                        $result = 1;
                    }
                    $this->log($this->from, $textarray_wp2[0], $this->to[$i], $result);
                    do_action('wp_sms_send', $result);

                    return $result;
                }

            } catch (\SoapFault $ex) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $ex->faultstring, 'error');

                return new \WP_Error('send-sms', $ex->faultstring);
            }
        } else {
            try {
                $client                 = new \SoapClient($this->wsdl_link);
                $parameters['username'] = $this->username;
                $parameters['password'] = $this->password;
                $parameters['from']     = $this->from;
                $parameters['to']       = $this->to;
                $parameters['text']     = $this->msg;
                $parameters['isflash']  = $this->isflash;
                $parameters['udh']      = "";
                $parameters['recId']    = array(0);
                $parameters['status']   = 0x0;

                $result = $client->SendSms($parameters)->SendSmsResult;


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
            } catch (\SoapFault $ex) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $ex->faultstring, 'error');

                return new \WP_Error('send-sms', $ex->faultstring);
            }
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', esc_html__('Username and Password are required.', 'wp-sms'));
        }

        if (!class_exists('SoapClient')) {
            return new \WP_Error('required-class', esc_html__('Class SoapClient not found. please enable php_soap in your php.', 'wp-sms'));
        }

        try {
            $client = new \SoapClient($this->wsdl_link);

            return $client->GetCredit(array(
                "username" => $this->username,
                "password" => $this->password
            ))->GetCreditResult;
        } catch (\SoapFault $ex) {
            return new \WP_Error('account-credit', $ex->faultstring);
        }
    }
}
