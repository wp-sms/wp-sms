<?php

namespace WP_SMS\Gateway;

class africastalking extends \WP_SMS\Gateway
{
    private $wsdl_link = 'https://api.africastalking.com/version1';
    public $tariff = "https://africastalking.com";
    public $unitrial = false;
    public $unit;
    public $flash = "false";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();

        $this->validateNumber = "+254711XXXYYY";
        $this->help           = "API key generated from your account settings";
        $this->bulk_send      = true;
        $this->has_key        = true;
        $this->gatewayFields  = [
            'username' => [
                'id'   => 'gateway_username',
                'name' => 'Registered Username',
                'desc' => 'Enter your username.',
            ],
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'Enter API key of gateway. You can avail it from your control panel.',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'Your registered short code or alphanumeric, defaults to AFRICASTKNG.',
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
                'username' => $this->username,
                'to'       => $this->to,
                'message'  => $this->msg,
                'from'     => $this->from
            ];

            $response = $this->request('POST', "{$this->wsdl_link}/messaging", [], $arguments);

            if (isset($response->statusCode) && $response->statusCode != '100') {
                throw new \Exception($response);
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
            // Check username and password
            if (!$this->username or !$this->has_key) {
                return new \WP_Error('account-credit', __('Username and API key are required.', 'wp-sms'));
            }

            return 1;

        } catch (\Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }
    }
}