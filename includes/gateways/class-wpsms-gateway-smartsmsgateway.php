<?php

namespace WP_SMS\Gateway;

class smartsmsgateway extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://smartsmsgateway.com/api";
    public $tariff = "https://smartsmsgateway.com";
    public $unitrial = false;
    public $unit;
    public $flash = "false";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->has_key        = false;
        $this->validateNumber = "";
        $this->help           = "Fill in the below fields with the valid credentials from your SMS gateway provider.";
        $this->gatewayFields  = [
            'username' => [
                'id'   => 'gateway_username',
                'name' => 'API Username',
                'desc' => 'Enter your API username.',
            ],
            'password' => [
                'id'   => 'gateway_password',
                'name' => 'API Password',
                'desc' => 'Enter your API password.',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'Enter your approved sender ID.',
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

            $type = 'text';

            if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
                $type = 'unicode';
            }

            $params = array(
                'username' => $this->username,
                'password' => $this->password,
                'senderid' => $this->from,
                'to'       => implode(',', $this->to),
                'text'     => $this->msg,
                'type'     => $type,
            );

            $response = $this->request('GET', "{$this->wsdl_link}/api_http.php", $params, []);

            if (strpos($response, 'ERROR')) {
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
            if (!$this->username or !$this->password) {
                throw new \Exception(esc_html__('The Username/Password for this gateway is not set.', 'wp-sms'));
            }

            $params = [
                'username' => $this->username,
                'password' => $this->password,
            ];

            $response = $this->request('GET', "{$this->wsdl_link}/api_http_balance.php", $params, []);

            if (strpos($response, 'ERROR')) {
                throw new \Exception($response);
            }

            return $response;
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            return new \WP_Error('account-credit', $error_message);
        }
    }
}
