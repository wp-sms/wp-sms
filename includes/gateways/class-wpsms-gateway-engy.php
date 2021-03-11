<?php

namespace WP_SMS\Gateway;

class engy extends \WP_SMS\Gateway
{
    private $wsdl_link = 'http://api.engy.solutions/';
    public $tariff = '';
    public $unitrial = true;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    public $bulk_send = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "491775156xxx";
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

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         * @since 3.4
         *
         */
        // $this->msg = apply_filters( 'wp_sms_msg', $this->msg );
        $args = array(
            'method'      => 'POST',
            'timeout'     => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(),
            'body'        => array('apiKey'                => $this->has_key,
                                   'from'                  => $this->from,
                                   'to'                    => implode(',', $this->to),
                                   'text'                  => $this->msg,
                                   'receiveDeliveryStatus' => true
            ),
            'cookies'     => array()
        );
        if (!is_numeric(implode(',', $this->to))) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, 'Please use a valid phone number (eg. ' . $this->validateNumber . ')', 'error');

            return new \WP_Error('send-sms', 'Please use a valid phone number (eg. ' . $this->validateNumber . ')');
        }
        if (strlen($this->msg) > 160) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, 'You can only send short messages for testing ( 160 characters max )', 'error');

            return new \WP_Error('send-sms', 'You can only send short messages for testing ( 160 characters max )');
        }
        $response = wp_remote_post($this->wsdl_link . "outbound/sms/", $args);
        // Check response error
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code == '200' || $response_code == '202') {
            $result = json_decode($response['body']);
            if (isset($result->status) and $result->status == 'ERR') {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result->error_string, 'error');

                return new \WP_Error('send-sms', $result->error_string);
            } else {
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

                return $response['body'];
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
        return 1;
    }
}