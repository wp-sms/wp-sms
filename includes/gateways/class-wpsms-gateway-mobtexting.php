<?php

namespace WP_SMS\Gateway;

class Mobtexting extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://portal.mobtexting.com/api/v2";
    public $tariff = "https://www.mobtexting.com/pricing.php";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "91[9,8,7,6]XXXXXXXXX";
        $this->help           = "Login authentication key (this key is unique for every user).<br>For BRAND Sender id Please Make it Approve Before Sending SMS";
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

        // comma seperated receivers
        $to            = implode(',', $this->to);
        $msg           = $this->msg;
        $api_end_point = $this->wsdl_link . "/sms/send";
        $api_args      = array(
            'access_token' => $this->has_key,
            'sender'       => $this->from,
            'message'      => $msg,
            'to'           => $to,
            'service'      => 'T'
        );

        $response = wp_remote_post($api_end_point, array('body' => $api_args, 'timeout' => 30));

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $result        = json_decode($response['body']);

        if ($response_code == '201') {
            if ($result->status == 'success') {
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
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result->message, 'error');

            return new \WP_Error('send-sms', $result->message);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }
        $api_end_point = $this->wsdl_link . "/account/balance";
        $api_args      = array(
            'timeout' => 18000
        );
        $response      = wp_remote_get($api_end_point . '?access_token=' . $this->has_key, $api_args);
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
            $result = (array)($result);
            foreach ($result['data'] as $key => $value) {
                $value = (array)($value);

                if ($value['service'] == "T") {
                    $credits = $value['credits'];
                }

            }
            if (isset($result->status) and $result->status != 'success') {
                return new \WP_Error('account-credit', $result->msg . $result->description);
            } else {
                return $credits;
            }
        } else {
            return new \WP_Error('account-credit', $response['body']);
        }
    }
}
