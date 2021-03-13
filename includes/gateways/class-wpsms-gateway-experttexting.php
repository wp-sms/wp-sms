<?php

namespace WP_SMS\Gateway;

class experttexting extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.experttexting.com/ExptRestApi/sms/";
    public $tariff = "http://experttexting.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "The number you want to send message to. Number should be in international format. Ex: to=17327572923";
        $this->has_key        = true;
        $this->help           = "You can find the API Key under \"Account Settings\" in <a href='https://www.experttexting.com/appv2/Dashboard/Profile'>ExpertTexting Profile</a>.";
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

        // Check unicode option if enabled.
        if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
            $text = $this->msg;
            $type = "unicode";
        } else {
            $text = urlencode($this->msg);
            $type = "text";
        }

        foreach ($this->to as $to) {
            $response = wp_remote_get($this->wsdl_link . "json/Message/Send?username=" . $this->username . "&password=" . $this->password . "&api_key=" . $this->has_key . "&from=" . $this->from . "&to=" . $to . "&text=" . $text . "&type=" . $type, array('timeout' => 30));
        }

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
            $json = json_decode($response['body']);

            if ($json->Status == 0) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response['body']);

                /**
                 * Run hook after send sms.
                 *
                 * @param string $response result output.
                 * @since 2.4
                 *
                 */
                do_action('wp_sms_send', $json);

                return $json;
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $json->ErrorMessage, 'error');

                return new \WP_Error('send-sms', $json->ErrorMessage);
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
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms-pro'));
        }

        if (false === ($response = get_transient('wp_sms_gateway_experttexting'))) {
            $response = wp_remote_get($this->wsdl_link . "json/Account/Balance?username={$this->username}&password={$this->password}&api_key={$this->has_key}", array('timeout' => 30));

            set_transient('wp_sms_gateway_experttexting', $response, 12 * HOUR_IN_SECONDS);
        }

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $json = json_decode($response['body']);

            if ($json->Status == 0) {
                return $json->Response->Balance;
            } else {
                return new \WP_Error('account-credit', $json->ErrorMessage);
            }

        } else {
            return new \WP_Error('account-credit', $response['body']);
        }

        return true;
    }
}