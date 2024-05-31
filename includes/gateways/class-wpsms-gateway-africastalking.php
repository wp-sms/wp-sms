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
    public $supportIncoming = true;

    public function __construct()
    {
        parent::__construct();

        $this->validateNumber = "";
        $this->help           = "You can generate an API key from the dashboard, here is an article from the help center on <a href='https://help.africastalking.com/en/articles/1361037-how-do-i-generate-an-api-key' target='_blank'>how to generate an API Key.</a>";
        $this->bulk_send      = true;
        $this->has_key        = true;
        $this->gatewayFields  = [
            'username' => [
                'id'   => 'gateway_username',
                'name' => 'Username',
                'desc' => 'Your Africa’s Talking application username.',
            ],
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'Africa’s Talking application apiKey.',
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
                'headers' => [
                    'apiKey'       => $this->has_key,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept'       => 'application/json'
                ],
                'body'    => [
                    'username' => $this->username,
                    'to'       => implode(',', $this->to),
                    'message'  => $this->msg,
                    'from'     => !empty($this->from) ? $this->from : null
                ]
            ];

            $response = $this->request('POST', "{$this->wsdl_link}/messaging", [], $arguments);

            if (isset($response) && empty($response->SMSMessageData->Recipients)) {
                throw new \Exception($response->SMSMessageData->Message);
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
            // Check username and password
            if (!$this->username or !$this->has_key) {
                return new \WP_Error('account-credit', esc_html__('Username and API key are required.', 'wp-sms'));
            }

            return 1;

        } catch (\Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }
    }
}