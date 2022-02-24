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
    public $gatewayFields = [
        'username' => [
            'id'   => 'gateway_username',
            'name' => 'Username',
            'desc' => 'Your ET username. Ex: username=starcity',
        ],
        'password' => [
            'id'   => 'gateway_password',
            'name' => 'API key',
            'desc' => 'Your API key (can be found in account settings). Ex: api_key=sswmp8r7l63y',
        ],
        'has_key'  => [
            'id'   => 'gateway_key',
            'name' => 'API secret',
            'desc' => 'Your API secret (can be found in account settings). Ex: api_secret=5fq8vn07iyoqu3j'
        ],
        'from'     => [
            'id'   => 'gateway_sender_id',
            'name' => 'Sender number',
            'desc' => 'Sender number or sender ID',
        ],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "The number you want to send message to. Number should be in international format. Ex: to=17327572923";
        $this->has_key        = true;
        $this->help           = "All requests require your user credentials & API key, which you can find under <b>Account Settings</b> in <a href='https://www.experttexting.com/appv2/Dashboard/Profile' target='_blank'>ExpertTexting Profile</a>.";
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
        if (empty($this->from))
            $this->from = "DEFAULT";
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
            $response = wp_remote_get($this->wsdl_link . "json/Message/Send?username=" . $this->username . "&api_key=" . $this->password . "&from=" . $this->from . "&api_secret=" . $this->has_key . "&to=" . $to . "&text=" . $text . "&type=" . $type, array('timeout' => 30));
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
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . "json/Account/Balance?username={$this->username}&api_key={$this->password}&api_secret={$this->has_key}", array('timeout' => 30));

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
