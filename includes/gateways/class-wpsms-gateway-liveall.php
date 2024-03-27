<?php

namespace WP_SMS\Gateway;

class liveall extends \WP_SMS\Gateway
{
    private $wsdl_link = 'https://sms.liveall.eu/apiext';
    public $tariff = "https://www.liveall.eu";
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
        $this->help           = "Fill the below fields with provided credentials by the SMS gateway provider.";
        $this->gatewayFields  = [
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => 'API Token',
                'desc' => 'A unique hash code for each account that authorizes each web request. That code you can find it on <a href="https://www.liveall.eu/user">your account’s page.</a>',
            ],
            'from'    => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'The sender name of the SMS. There is a limit to 11 characters (latin characters). Allowed characters are: [A-Za-z0-9\-\.\!\#\%\&\(\)\<\>]',
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

            // Get the credit.
            $credit = $this->GetCredit();

            // Check gateway credit
            if (is_wp_error($credit)) {
                throw new \Exception($credit->get_error_message());
            }

            $params = [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body'    => wp_json_encode([
                    'apitoken' => $this->has_key,
                    'senderid' => $this->from,
                    'messages' => array_map(function ($number) {
                        return [
                            'destination' => $number,
                            'message'     => $this->msg
                        ];
                    }, $this->to),
                ])
            ];
            
            $response = $this->request('POST', "{$this->wsdl_link}/Sendout/SendJSMS", [], $params);

            if (isset($response->success) && !$response->success) {
                throw new \Exception($response->OperationErrors[0]->errorMessage);
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

            // Check API key
            if (!$this->has_key) {
                throw new \Exception(esc_html__('The API Token for this gateway is not set.', 'wp-sms'));
            }

            return 1;

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            return new \WP_Error('account-credit', $error_message);
        }

    }

}