<?php

namespace WP_SMS\Gateway;

class smsglobal extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.smsglobal.com/v2";
    public $tariff = "https://smsglobal.com";
    public $unitrial = false;
    public $unit;
    public $flash = "false";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "The number starting with country code.";
        $this->help           = "Please fill the below fields. Information on managing API keys can be found <a href='https://knowledgebase.smsglobal.com/en/articles/5186368-how-to-integrate-with-an-api-in-mxt-video' target='_blank'>here</a>.";
        $this->has_key        = true;
        $this->gatewayFields  = [
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'Enter your API key that is issued by SMSGlobal.',
            ],
            'password' => [
                'id'   => 'gateway_password',
                'name' => 'API Secret',
                'desc' => 'Enter the API secret that is issued with your API key.',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'Enter your registered and approved sender name. <a href="https://smsportal.hostpinnacle.co.ke/user/info/?action=sender-id" target="_blank">More info?</a>',
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

            // Get the Credit.
            $credit = $this->GetCredit();

            // Check gateway credit
            if (is_wp_error($credit)) {
                throw new \Exception($credit->get_error_message());
            }

            $time  = time();
            $nonce = mt_rand();

            $mac = array(
                $time,
                $nonce,
                'POST',
                '/v2/sms',
                'api.smsglobal.com',
                '443',
                '',
            );

            $mac  = sprintf("%s\n", implode("\n", $mac));
            $hash = hash_hmac('sha256', $mac, $this->password, true);
            $mac  = base64_encode($hash);

            $arguments = [
                'headers' => [
                    'Authorization' => 'MAC id="' . $this->has_key . '", ts="' . $time . '", nonce="' . $nonce . '", mac="' . $mac . '"',
                    'Content-Type'  => 'application/json'
                ],
                'body'    => json_encode([
                    'destinations' => explode(',', implode(',', $this->to)),
                    'message'      => $this->msg,
                    'origin'       => $this->from,
                ])
            ];

            $response = $this->request('POST', "{$this->wsdl_link}/sms", [], $arguments);

            // Check response
            if (isset($response->code) && $response->code !== '200') {
                throw new \Exception($response->message);
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
            // Check API key and password
            if (!$this->password or !$this->has_key) {
                throw new \Exception(__('The API Key or API Secret for this gateway is not set', 'wp-sms'));
            }

            $time  = time();
            $nonce = mt_rand();

            $mac = array(
                $time,
                $nonce,
                'GET',
                '/v2/user/credit-balance',
                'api.smsglobal.com',
                '443',
                '',
            );

            $mac  = sprintf("%s\n", implode("\n", $mac));
            $hash = hash_hmac('sha256', $mac, $this->password, true);
            $mac  = base64_encode($hash);

            $arguments = [
                'headers' => [
                    'Authorization' => 'MAC id="' . $this->has_key . '", ts="' . $time . '", nonce="' . $nonce . '", mac="' . $mac . '"',
                    'Content-Type'  => 'application/json'
                ]
            ];

            $response = $this->request('GET', "{$this->wsdl_link}/user/credit-balance", [], $arguments);

            // Check response
            if (isset($response->code) && $response->code !== '200') {
                throw new \Exception($response->message);
            }

            return $response->balance . ' ' . $response->currency;

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            return new \WP_Error('account-credit', $error_message);
        }

    }
}