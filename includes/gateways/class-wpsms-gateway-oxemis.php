<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class oxemis extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.oxisms.com/api/1.0";
    public $tariff = "https://www.oxemis.com/en/sms";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->has_key        = true;
        $this->bulk_send      = true;
        $this->validateNumber = "Example: 0033601234567";
        $this->help           = "";
        $this->gatewayFields  = [
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'API ID',
                'desc' => 'Enter your API ID.'
            ],
            'password' => [
                'id'   => 'gateway_password',
                'name' => 'API Password',
                'desc' => 'Enter your API Password.',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'SMS Sender name or number',
                'desc' => 'Enter your SMS Sender name or number.',
            ],
        ];
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

        try {

            $arguments = [
                'api_key'      => $this->has_key,
                'api_password' => $this->password,
                'message'      => urlencode($this->msg),
                'recipients'   => implode(',', $this->to),
                'sender'       => $this->from
            ];

            $response = $this->request('POST', "$this->wsdl_link/send.php", $arguments, []);

            if (!isset($response->success) && isset($response->message)) {
                return new Exception($response->message);
            }

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             *
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

            return $response;

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        try {

            // Check API key and API Password
            if (!$this->has_key || !$this->password) {
                return new WP_Error('account-credit', __('The API Key and API Password are required.', 'wp-sms'));
            }

            $arguments = [
                'api_key'      => $this->has_key,
                'api_password' => $this->password
            ];

            $response = $this->request('POST', "$this->wsdl_link/account.php", $arguments, []);

            if (!isset($response->success) && isset($response->message)) {
                return new Exception($response->message);
            }

            if (!isset($response->details->credit)) {
                return $response;
            }

            return $response->details->credit;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }
}