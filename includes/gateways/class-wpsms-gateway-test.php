<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class test extends \WP_SMS\Gateway
{
    private $wsdl_link = '';
    public $tariff = '';
    public $unitrial = true;
    public $unit;
    public $flash = "false";
    public $isflash = false;
    public $options;

    public function __construct()
    {
        parent::__construct();
        $this->help           = "";
        $this->validateNumber = "09xxxxxxxx";
        $this->has_key        = true;
        $this->bulk_send      = true;
        $this->gatewayFields  = [
            'from' => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'Enter your sender ID',
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
            $params = [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body'    => wp_json_encode([
                    'action'    => 'send-sms',
                    'sender_id' => $this->from,
                    'recipient' => implode(',', $this->to),
                    'message'   => $this->msg
                ])
            ];

            // generate a randomized fake response
            $response = [
                'success'    => true,
                'status'     => 'sent',
                'message_id' => uniqid('sms_'),
                'from'       => $this->from,
                'to'         => $this->to,
                'recipients' => is_array($this->to) ? count($this->to) : 1,
                'message'    => $this->msg,
                'cost'       => sprintf('%.2f USD', wp_rand(5, 500) / 100),
                'sent_at'    => current_time('mysql'),
                'error'      => null,
                'raw'        => [
                    'params'   => $params,
                    'debug_id' => wp_rand(100000, 999999),
                ],
            ];

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

        } catch (\Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        return '143 USD';
    }
}