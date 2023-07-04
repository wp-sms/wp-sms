<?php

namespace WP_SMS\Gateway;

use Exception;

class prosmsdk extends \WP_SMS\Gateway
{
    private $wsdl_link = 'https://api.sms.dk/v1';
    public $tariff = 'https://prosms.se';
    public $unitrial = false;
    public $unit;
    public $flash = "false";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();

        $this->validateNumber = "";
        $this->help           = "You can generate an API key from the dashboard, here is an article from the help center on <a href='https://help.africastalking.com/en/articles/1361037-how-do-i-generate-an-api-key' target='_blank'>how to generate an API Key.</a>";
        $this->bulk_send      = true;
        $this->has_key        = true;
        $this->gatewayFields  = [
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'Your API key.',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender Name',
                'desc' => 'Set the number that the receiver will see as the sender of the SMS. It can be either numeric with a limit of 15 chars or alphanumeric with a limit of 11 chars. Your sender names needs to be validated in the web interface.',
            ]
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
                'headers' => [
                    'Authorization'       => "Bearer {$this->has_key}",
                    'Content-Type' => 'application/json'
                ],
                'body'    => json_encode([
                    'receiver' => implode(',', $this->to),
                    'message'  => $this->msg,
                    'senderName'     => trim($this->from),
                ])
            ];

            $response = $this->request('POST', "{$this->wsdl_link}/sms/send", [], $arguments);

            if (isset($response) && $response->status !== 'success') {
                throw new Exception($response);
            }

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

    public
    function GetCredit()
    {
        try {
            // Check API key
            if (!$this->has_key) {
                return new \WP_Error('account-credit', __('API key is required.', 'wp-sms'));
            }

            $arguments = [
                'headers' => [
                    'Authorization' => "Bearer {$this->has_key}"
                ]
            ];

            $response = $this->request('GET', "{$this->wsdl_link}/user/getcreditvalue", [], $arguments);

            if (isset($response) && $response->status !== 'success') {
                throw new Exception($response);
            }

            return $response->result;
        } catch (\Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }
    }
}
