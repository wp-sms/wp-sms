<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class oursms extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.oursms.com";
    public $tariff = "https://www.oursms.net";
    public $unitrial = false;
    public $unit;
    public $flash = "false";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->has_key        = true;
        $this->validateNumber = "Separate numbers between them with comma ( , ) Numbers must be entered in international format 966500000000 and international messages without 00 or +";
        $this->help           = "To get API token, <a href='https://www.youtube.com/watch?v=UfZlQ4wq3JA' target='_blank'>watch This video</a>";
        $this->gatewayFields  = [
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => 'API Token',
                'desc' => 'Please enter API token of gateway. You can avail it from your control panel.',
            ],
            'from'    => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender Name',
                'desc' => 'Please enter your Sender Name or sender ID.',
            ],
        ];
    }

    public function SendSMS()
    {
        /**
         * Modify sender number
         *
         * @param string $this - >from sender number.
         *
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this - >to receiver number
         *
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this - >msg text message.
         *
         * @since 3.4
         *
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {

            if (empty($this->from)) {
                $this->from = 'OurSms';
            }

            // +9661234
            // 009661234
            // 09661234
            // 9661234

            $to        = $this->cleanNumbers($this->to);
            $arguments = array(
                'headers' => [
                    'Authorization' => "Bearer {$this->has_key}",
                    'Content-Type'  => 'application/json'
                ],
                'body'    => json_encode([
                    'src'   => $this->from,
                    'dests' => $to,
                    'body'  => $this->msg,
                ])
            );

            // Get Send SMS Response
            $response = $this->request('POST', "{$this->wsdl_link}/msgs/sms", [], $arguments);

            // Error Handler
            if (isset($response->errorCode)) {
                throw new Exception($response->message);
            }

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
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

            // Check username and password
            if (!$this->has_key) {
                return new WP_Error('account-credit', __('API Token is required.', 'wp-sms'));
            }

            $params = array(
                'headers' => [
                    'Authorization' => "Bearer {$this->has_key}"
                ]
            );

            // Get Credit Response
            $response = $this->request('GET', "{$this->wsdl_link}/billing/credits", [], $params);

            if (isset($response->errorCode)) {
                throw new Exception($response->message);
            }

            return $response->credits;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }
}