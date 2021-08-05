<?php

namespace WP_SMS\Gateway;

class msgwow extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://my.msgwow.com/api/";
    public $tariff = "http://msgwow.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "919999999999";
        $this->help           = "Login authentication key (this key is unique for every user).<br>The default route number is 4 and you can set your route number in sender number. e.g. 100000:4 or 100000:2";
        $this->has_key        = true;
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

        $from = explode(':', $this->from);
        if (is_array($from) and isset($from[1])) {
            $route = $from[1];
        } else {
            $route = 4;
        }

        // Unicode message
        $msg = urlencode($this->msg);

        $response = wp_remote_get($this->wsdl_link . "v2/sendsms?authkey=" . $this->has_key . "&mobiles=" . $to . "&message=" . $msg . "&sender=" . $this->from . "&route=" . $route . "&country=0", array('timeout' => 30));

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $result        = json_decode($response['body']);

        if ($response_code == '200') {
            if ($result->type == 'success') {
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
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result->message, 'error');

                return $result->message;
            }

        } else {
            return new \WP_Error('send-sms', $result->message);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . "balance.php?authkey=" . $this->has_key . "&type=4", array('timeout' => 30));

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

            if (isset($result->msgType) and $result->msgType == 'error') {
                return new \WP_Error('account-credit', $result->msg . ' (See error codes: http://my.msgwow.com/apidoc/basic/error-code-basic.php)');
            } else {
                return $result;
            }
        } else {
            return new \WP_Error('account-credit', $response['body']);
        }
    }
}