<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class smsapi extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.smsapi.pl";
    public $tariff = "https://smsapi.pl";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "48500500500 or with country code";
        $this->help           = 'Generating a token with access to selected zones can be done in the <a href="https://ssl.smsapi.pl/" target="_blank">Tokens API</a> client panel .';
        $this->bulk_send      = true;
        $this->has_key        = true;
        $this->gatewayFields  = [
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => 'API Token',
                'desc' => 'Please enter your API Token.',
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

          $messageBody = $this->msg;

          if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
              $messageBody = $this->convertToUnicode($messageBody);
          }

            $arguments = [
                'headers' => [
                    'Authorization' => "Bearer {$this->has_key}"
                ],
                'body'    => [
                    'to'      => implode(',', $this->to),
                    'from'    => urlencode($this->from),
                    'message' => $messageBody,
                    'format'  => 'json'
                ]
            ];

            $response = $this->request('POST', "{$this->wsdl_link}/sms.do", [], $arguments);

            if (isset($response->error)) {
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
        // API Token validation
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('API Token is required.', 'wp-sms'));
        }

        return 1;
    }
}
