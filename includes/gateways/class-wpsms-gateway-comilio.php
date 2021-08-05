<?php

namespace WP_SMS\Gateway;

class comilio extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.comilio.it/rest/v1";
    public $tariff = "https://www.comilio.it/tariffe";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "";
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

        $payload = array(
            'message_type'  => 'SmartPro',
            'phone_numbers' => $this->to,
            'text'          => $this->msg,
            'sender_string' => $this->from
        );

        $response = wp_remote_post($this->wsdl_link . '/message', [
            'timeout' => 10,
            'body'    => json_encode($payload),
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                'Content-Type'  => 'application/json',
            ]
        ]);

        $status  = wp_remote_retrieve_response_code($response);
        $result  = wp_remote_retrieve_body($response);
        $jsonObj = json_decode($result);

        if (isset($jsonObj->error) or $status != 200) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $status, 'error');

            return new \WP_Error('send-sms', $jsonObj->error);
        } else {
            $result = $jsonObj->message_id;

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

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . '/credits', [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
            ]
        ]);

        $status = wp_remote_retrieve_response_code($response);
        $result = wp_remote_retrieve_body($response);

        if ($result) {
            $jsonObj = json_decode($result);

            if (null === $jsonObj) {
                return new \WP_Error('account-credit', $result);
            } elseif ($status != 200) {
                return new \WP_Error('account-credit', $result);
            } else {
                for ($i = 0; $i < count($jsonObj); $i++) {
                    if ($jsonObj[$i]->message_type === 'SmartPro') {
                        return $jsonObj[$i]->quantity;
                    }
                }

                return new \WP_Error('account-credit', $result);
            }
        } else {
            return new \WP_Error('account-credit', $result);
        }
    }
}
