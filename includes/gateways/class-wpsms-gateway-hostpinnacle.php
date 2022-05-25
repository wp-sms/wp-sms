<?php

namespace WP_SMS\Gateway;

class hostpinnacle extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://smsportal.hostpinnacle.co.ke/SMSApi";
    public $tariff = "https://www.hostpinnacle.co.ke/";
    public $unitrial = false;
    public $unit;
    public $flash = "false";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->has_key = true;
        $this->validateNumber = "";
        $this->help           = "If you are using API Key, then you don't need to enter your username and password.";
        $this->gatewayFields  = [
            'username' => [
                'id'   => 'gateway_username',
                'name' => 'Registered Username',
                'desc' => 'Enter your username.',
            ],
            'password' => [
                'id'   => 'gateway_password',
                'name' => 'Password',
                'desc' => 'Enter your password.',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'Enter your registered and approved sender name. <a href="https://smsportal.hostpinnacle.co.ke/user/info/?action=sender-id" target="_blank">More info?</a>',
            ],
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'Enter API key of gateway. You can avail it from your control panel.',
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

            $msg_type = isset($this->options['send_unicode']) ? 'text' : 'unicode';

            $response = $this->request('POST', "{$this->wsdl_link}/send", [], [
                'headers' => [
                    'apiKey' => $this->has_key
                ],
                'body'    => [
                    'userid'     => $this->username,
                    'password'   => $this->password,
                    'sendMethod' => 'quick',
                    'mobile'     => implode(',', $this->to),
                    'msg'        => urlencode($this->msg),
                    'senderid'   => $this->from,
                    'msgType'    => $msg_type,
                    'output'     => 'json'
                ]
            ]);

            if (isset($response->status) && $response->status == 'error') {
                throw new \Exception($response->reason);
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
            if (!$this->username or !$this->password) {
                throw new \Exception(__('The username/password for this gateway is not set.', 'wp-sms'));
            }

            $params = [
                'userid' => $this->username,
                'password' => $this->password,
                'output' => 'json'
            ];

            $response = $this->request('POST', "{$this->wsdl_link}/account/readstatus", $params, []);

            if ($response->response->code !== '200') {
                throw new \Exception($response->response->msg);
            }

            return $response->response->account->smsBalance;

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            return new \WP_Error('account-credit', $error_message);
        }

    }

}