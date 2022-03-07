<?php

namespace WP_SMS\Gateway;

class pridesms extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://pridesms.in/api/v1/";
    public $tariff = "http://pridesms.in/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "e.g. 9029963999";
        $this->help           = 'Please enter Route ID in API Key field';
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

        $response = wp_remote_get($this->wsdl_link . "sendSMS.php?user=" . $this->username . "&password=" . $this->password . "&senderid=" . $this->from . "&number=" . implode(',', $this->to) . "&text=" . urlencode($this->msg));

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');
            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Ger response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Check response code
        if ($response_code == '200') {
            $response = json_decode($response['body']);

            if ($response->ErrorCode == '000') {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response);

                /**
                 * Run hook after send sms.
                 *
                 * @param string $response result output.
                 * @since 2.4
                 *
                 */
                do_action('wp_sms_send', $response);

                return $response;
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response->ErrorMessage, 'error');
                return new \WP_Error('send-sms', $response->ErrorMessage);
            }

        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');
            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username or !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . "getBalance.php?user={$this->username}&password={$this->password}");

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $response = json_decode($response['body']);

            if ($response->ErrorCode == '000') {
                return $response->Balance;
            } else {
                return new \WP_Error('account-credit', $response->ErrorMessage);
            }

        } else {
            return new \WP_Error('account-credit', $response['body']);
        }

        return true;
    }
}