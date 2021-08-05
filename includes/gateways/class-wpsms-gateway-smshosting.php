<?php

namespace WP_SMS\Gateway;

class smshosting extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.smshosting.it/rest/api";
    public $tariff = "https://www.smshosting.it/en/pricing";
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

        $to = implode(",", $this->to);

        $response = wp_remote_post($this->wsdl_link . '/sms/send', [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                'Content-Type'  => 'application/x-www-form-urlencoded'
            ],
            'body'    => [
                'to'   => $to,
                'from' => $this->from,
                'text' => $this->msg
            ]
        ]);

        $result = wp_remote_retrieve_body($response);

        $status = wp_remote_retrieve_response_code($response);

        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        } else {
            $jsonObj = json_decode($result);

            if (null === $jsonObj) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $jsonObj, 'error');

                return false;
            } elseif ($status != 200) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $jsonObj, 'error');

                return false;
            } else {
                $result = $jsonObj->transactionId;

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
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . '/user', [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
            ]
        ]);

        $result = wp_remote_retrieve_body($response);

        $status = wp_remote_retrieve_response_code($response);

        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        } else {
            if ($result) {
                $jsonObj = json_decode($result);

                if (null === $jsonObj) {
                    return new \WP_Error('account-credit', $result);
                } elseif ($status != 200) {
                    return new \WP_Error('account-credit', $result);
                } else {
                    return $jsonObj->italysms;
                }
            } else {
                return new \WP_Error('account-credit', $result);
            }
        }
    }
}
