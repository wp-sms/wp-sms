<?php

namespace WP_SMS\Gateway;

class smss extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://api.smss.co.il/";
    public $tariff = "";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "The phone number of the message recipient. It should be a valid phone number in E164 format";
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

        $postdata = http_build_query(
            array(
                'user'      => $this->username,
                'password'  => $this->password,
                'from'      => $this->from,
                'recipient' => implode(',', $this->to),
                'message'   => $this->msg,
            )
        );

        $opts     = array(
            'http' =>
                array(
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $postdata
                )
        );
        $context  = stream_context_create($opts);
        $response = file_get_contents('MultiSendAPI/sendsms', false, $context);

        $result = json_decode($response);

        if ($result->success) {
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

        return new \WP_Error('send-sms', print_r($result));
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $response = file_get_contents($this->wsdl_link . 'MultiSendAPI/balance&user=' . $this->username . '&password=' . $this->password . '&country_phone_code=' . $this->options['mobile_county_code']);
        $result   = json_decode($response);

        if ($result->success) {
            return $result->sms;
        }

        return new \WP_Error('account-credit', print_r($result));
    }
}