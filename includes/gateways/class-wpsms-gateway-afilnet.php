<?php

namespace WP_SMS\Gateway;

class afilnet extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.afilnet.com/api/http/";
    public $tariff = "http://www.afilnet.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "34600000000";
        $this->bulk_send      = false;
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

        // Implode numbers
        $to = implode(',', $this->to);

        // Unicode message
        $msg = urlencode($this->msg);

        $response = wp_remote_get($this->wsdl_link . "?class=sms&method=sendsms&user=" . $this->username . "&password=" . $this->password . "&from=" . $this->from . "&to=" . $this->to[0] . "&sms=" . $this->msg . "&scheduledatetime=&output=", array('timeout' => 30));

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $result = json_decode($response['body']);

            if ($result->status == 'SUCCESS') {
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

                return $result->result;
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result->error, 'error');

                return new \WP_Error('send-sms', $result->error);
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
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . "?class=user&method=getbalance&user=" . $this->username . "&password=" . $this->password, array('timeout' => 30));

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            if (!$response['body']) {
                return new \WP_Error('account-credit', __('Server API Unavailable', 'wp-sms'));
            }

            $result = json_decode($response['body']);

            if ($result->status == 'SUCCESS') {
                return $result->result;
            } else {
                return new \WP_Error('account-credit', $result->error);
            }
        } else {
            return new \WP_Error('account-credit', $response['body']);
        }
    }
}