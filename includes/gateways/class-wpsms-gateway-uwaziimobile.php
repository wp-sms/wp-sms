<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class uwaziimobile extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api2.uwaziimobile.com";
    public $tariff = "https://www.uwaziimobile.com";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send = true;
        $this->has_key = true;
        $this->validateNumber = "Destination addresses must be in international format (Example: 254722123456).";
        $this->help = "Enter your Gateway Token and Sender ID. You can avail them from your control panel.";
        $this->gatewayFields = [
            'has_key' => [
                'id' => 'gateway_key',
                'name' => 'Gateway Token',
                'desc' => 'Enter your Gateway Token.',
            ],
            'from' => [
                'id' => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'Enter the Sender ID',
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

            // Get the credit.
            $credit = $this->GetCredit();

            // Check gateway credit
            if (is_wp_error($credit)) {
                throw new \Exception($credit->get_error_message());
            }

            $response = $this->request('GET', "{$this->wsdl_link}/send", [
                [
                    'token' => $this->has_key,
                    'phone' => implode(',', $this->to),
                    'senderID' => $this->from,
                    'text' => $this->msg,
                ],
            ], []);

            //log the result
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

        } catch (Exception $e) {

            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');
            return new \WP_Error('send-sms', $e->getMessage());

        }
    }

    public function GetCredit()
    {

        try {

            // Check username and password
            if (!$this->has_key) {
                throw new Exception(__('Gateway Token is not entered.', 'wp-sms'));
            }

            return 1;

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            return new \WP_Error('account-credit', $error_message);
        }

    }
}