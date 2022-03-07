<?php

namespace WP_SMS\Gateway;

class smsozone extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://smsozone.com/api/mt/";
    public $tariff = "http://ozonecmc.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "e.g. 91989xxxxxxx";
        $this->has_key        = true;
        $this->help           = "Enter the route id in this API key field. Click Here (https://smsozone.com/Web/MT/MyRoutes.aspx) for more information regarding your routeid.";
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

        $response = wp_remote_get($this->wsdl_link . "SendSMS?user=" . $this->username . "&password=" . $this->password . "&senderid=" . $this->from . "&channel=Trans&DCS=0&flashsms=0&number=" . implode(',', $this->to) . "&text=" . urlencode($this->msg) . "&route=" . $this->has_key);

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Ger response code
        $response_code = wp_remote_retrieve_response_code($response);
        $json          = json_decode($response['body']);
        // Check response code
        if ($response_code == '200') {
            if ($json->ErrorCode == 0) {
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

                return $json;
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $json->ErrorMessage, 'error');

                return new \WP_Error('send-sms', $json->ErrorMessage);
            }

        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $json->ExceptionMessage, 'error');

            return new \WP_Error('send-sms', $json->ExceptionMessage);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username or !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . "GetBalance?User={$this->username}&Password={$this->password}");

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $json = json_decode($response['body']);

            if ($json->ErrorCode == 0) {
                return $json->Balance;
            } else {
                return new \WP_Error('account-credit', $json->ErrorMessage);
            }

        } else {
            return new \WP_Error('account-credit', $response['body']);
        }

        return true;
    }
}