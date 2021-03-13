<?php

namespace WP_SMS\Gateway;

class smsglobal extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.smsglobal.com/v2/";
    public $tariff = "https://smsglobal.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "The number starting with country code.";
        $this->help           = "Fill Api key as your API and use API Password as API Secret and leave empty the API username.";
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

        $time  = time();
        $nonce = mt_rand();

        $mac = array(
            $time,
            $nonce,
            'POST',
            '/v2/sms',
            'api.smsglobal.com',
            '443',
            '',
        );

        $mac  = sprintf("%s\n", implode("\n", $mac));
        $hash = hash_hmac('sha256', $mac, $this->password, true);
        $mac  = base64_encode($hash);

        $headers = array(
            'Authorization' => 'MAC id="' . $this->has_key . '", ts="' . $time . '", nonce="' . $nonce . '", mac="' . $mac . '"',
            'Content-Type'  => 'application/json'
        );

        $body = array(
            'destinations' => explode(',', implode(',', $this->to)),
            'message'      => $this->msg,
            'origin'       => $this->from,
        );

        $response = wp_remote_post($this->wsdl_link . 'sms', [
            'headers' => $headers,
            'body'    => json_encode($body)
        ]);

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $result        = json_decode($response['body']);
        $response_code = wp_remote_retrieve_response_code($response);

        if (is_object($result)) {
            if ($response_code == '200') {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result);

                /**
                 * Run hook after send sms.
                 *
                 * @since 2.4
                 */
                do_action('wp_sms_send', $result);

                return $result;
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result->errors, 'error');

                return new \WP_Error('send-sms', print_r($result->errors, 1));
            }
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', print_r($response['body'], 1));
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->password && !$this->has_key) {
            return new \WP_Error('account-credit', __('Username/API-Key does not set for this gateway', 'wp-sms'));
        }

        $time  = time();
        $nonce = mt_rand();

        $mac = array(
            $time,
            $nonce,
            'GET',
            '/v2/user/credit-balance',
            'api.smsglobal.com',
            '443',
            '',
        );

        $mac  = sprintf("%s\n", implode("\n", $mac));
        $hash = hash_hmac('sha256', $mac, $this->password, true);
        $mac  = base64_encode($hash);

        $headers = array(
            'Authorization' => 'MAC id="' . $this->has_key . '", ts="' . $time . '", nonce="' . $nonce . '", mac="' . $mac . '"',
            'Content-Type'  => 'application/json'
        );

        $response = wp_remote_get($this->wsdl_link . 'user/credit-balance', [
            'headers' => $headers
        ]);

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $result = json_decode($response['body']);

        $response_code = wp_remote_retrieve_response_code($response);
        if (is_object($result)) {
            if ($response_code == '200') {
                return $result->balance;
            } else {
                return new \WP_Error('credit', $result->error->message);
            }
        } else {
            return new \WP_Error('credit', $response['body']);
        }
    }
}