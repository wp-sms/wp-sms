<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class sendapp extends \WP_SMS\Gateway
{
    private $wsdl_link = 'https://sms.sendapp.live';
    public $tariff = "https://sendapp.live";
    public $unitrial = false;
    public $unit;
    public $flash = "false";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->has_key        = true;
        $this->validateNumber = "";
        $this->help           = "";
        $this->gatewayFields  = [
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'Get the API Key from your SendApp account in https://sms.sendapp.live/api.php',
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

            $response = [];

            foreach ($this->to as $number) {

                $params = array(
                    'number'  => $number,
                    'message' => $this->msg,
                    'key'     => $this->has_key
                );

                $result = $this->request('GET', "{$this->wsdl_link}/services/send.php", $params, []);

                if (isset($result->error) && !$result->success) {
                    throw new Exception($result->error->message);
                }

                $response[] = $result->data;

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

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        try {
            if (!$this->has_key) {
                throw new Exception(esc_html__('The API Key required.', 'wp-sms'));
            }

            $params = [
                'key' => trim($this->has_key)
            ];

            $response = $this->request('GET', "{$this->wsdl_link}/services/send.php", $params, []);

            if (isset($response->error) && !$response->success) {
                throw new Exception($response->error->message);
            }

            return $response->data->credits;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }
}