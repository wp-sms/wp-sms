<?php

namespace WP_SMS\Gateway;

class sendappWhatsApp extends \WP_SMS\Gateway
{
    private $wsdl_link = 'https://sendapp.cloud/api/send.php';
    public $tariff = "https://sendapp.cloud";
    public $unitrial = false;
    public $unit;
    public $flash = "false";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = false;
        $this->has_key        = true;
        $this->validateNumber = "84933313xxx";
        $this->help           = "";
        $this->gatewayFields  = [
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => 'Access Token',
                'desc' => 'Get the Access Token from your SendApp account in https://sendapp.cloud/dashboard/index/whatsapp',
            ],
            'from'    => [
                'id'   => 'gateway_sender_id',
                'name' => 'Instance ID',
                'desc' => 'Instance ID',
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

            $response = $this->executeSendMessage($this->to[0]);

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
                throw new \Exception(esc_html__('The API Key Key for this gateway is not set.', 'wp-sms'));
            }

            return 1;

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            return new \WP_Error('account-credit', $error_message);
        }
    }

    private function executeSendMessage($number)
    {
        $response = $this->request('GET', $this->wsdl_link, [
            'access_token' => $this->has_key,
            'instance_id'  => $this->from,
            'number'       => $number,
            'type'         => 'text',
            'message'      => urlencode($this->msg),
        ]);

        if ($response->status == 'error') {
            throw new \Exception(esc_html($response->message));
        }

        return $response->data;
    }
}