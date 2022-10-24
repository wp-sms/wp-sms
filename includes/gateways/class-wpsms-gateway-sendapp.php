<?php

namespace WP_SMS\Gateway;

class sendapp extends \WP_SMS\Gateway
{
    private $wsdl_link = 'https://sms.sendapp.live/services/send.php';
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
        $this->validateNumber = "+11234567890";
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
                $response[] = $this->executeSendSMS($number);
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

    public function GetCredit()
    {
        try {
            if (!$this->has_key) {
                throw new \Exception(__('The API Key Key for this gateway is not set.', 'wp-sms'));
            }

            $response = $this->request('GET', $this->wsdl_link, [
                'key' => $this->has_key
            ]);

            if ($response->error) {
                throw new \Exception($response->error->message);
            }

            return $response->data->credits;

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            return new \WP_Error('account-credit', $error_message);
        }
    }

    private function executeSendSMS($number)
    {
        $response = $this->request('GET', $this->wsdl_link, [
            'key'     => $this->has_key,
            'number'  => $number,
            'message' => urlencode($this->msg),
        ]);

        if ($response->error) {
            throw new \Exception($response->error->message);
        }

        return $response->data;
    }
}