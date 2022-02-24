<?php

namespace WP_SMS\Gateway;

class sabanovin extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://api.sabanovin.com/v1/";
    public $tariff = "http://sabanovin.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->has_key = true;
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

        $to       = implode(',', $this->to);
        $response = wp_remote_get($this->wsdl_link . $this->has_key . "/sms/send.json?gateway=" . $this->from . "&text=" . urlencode($this->msg) . "&to=" . $to, array('timeout' => 30));

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log th result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Ger response code
        $response_code = wp_remote_retrieve_response_code($response);
        $json          = json_decode($response['body']);

        if ($response_code == '200') {
            if ($json->status->code == 200) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $json);

                /**
                 * Run hook after send sms.
                 *
                 * @param string $response result output.
                 * @since 2.4
                 *
                 */
                do_action('wp_sms_send', $json);

                return $json->entries;
            } else {
                // Log th result
                $this->log($this->from, $this->msg, $this->to, $json->status->message, 'error');

                return new \WP_Error('send-sms', $json->status->message);
            }
        } else {
            // Log th result
            $this->log($this->from, $this->msg, $this->to, $json->status->message, 'error');

            return new \WP_Error('send-sms', $json->status->message);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('The API Key for this gateway is not set', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . $this->has_key . "/credit.json", array('timeout' => 30));

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $json          = json_decode($response['body']);

        if ($response_code == '200') {
            if ($json->status->code == 200) {
                return $json->entry->credit;
            } else {
                return new \WP_Error('account-credit', $json->status->message);
            }
        } else {
            return new \WP_Error('account-credit', $json->status->message);
        }
    }
}