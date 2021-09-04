<?php

namespace WP_SMS\Gateway;

class fortytwo extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://rest.fortytwo.com/1/";
    public $tariff = "http://fortytwo.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "Number must be in international format and can only be between 7-20 digits long. First digit cannot be a 0";
        $this->has_key        = true;
        $this->help           = 'The API token is generated through the Client Control Panel (https://controlpanel.fortytwo.com/), in the tokens section, under the IM tab.';
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

        // Reformat number
        $to = array();
        foreach ($this->to as $number) {
            $to[] = array('number' => $number);
        }

        $args = array(
            'headers' => array(
                'Authorization' => 'Token ' . $this->has_key,
                'Content-Type'  => 'application/json; charset=utf-8',
            ),
            'body'    => json_encode(array(
                'destinations' => $to,
                'sms_content'  => array(
                    'sender_id' => $this->from,
                    'message'   => $this->msg,
                )
            ))
        );

        $response = wp_remote_post($this->wsdl_link . "im", $args);

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Ger response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Decode response
        $response = json_decode($response['body']);

        // Check response code
        if ($response_code == '200') {
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
            $this->log($this->from, $this->msg, $this->to, $response->result_info->description, 'error');

            return new \WP_Error('account-credit', $response->result_info->description);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        return true;
    }
}