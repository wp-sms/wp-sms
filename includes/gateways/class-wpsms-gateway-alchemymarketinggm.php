<?php

namespace WP_SMS\Gateway;

class alchemymarketinggm extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://alchemytelco.com:port/api";
    public $tariff = "https://alchemytelco.com";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    public $gateway_port;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = false;
        $this->has_key        = false;
        $this->validateNumber = "The telephone number can be specified in local number format (e.g. 7654321), or in international number format (e.g.+447946318520). More then one recipient addresses can be separated by a colon (e.g.: +447949876543, +447920222333).";
        $this->help           = "Please fill in the below-required fields to send SMS through the Alchemy gateway. You must contact their support team to get the details of the port.";
        $this->gatewayFields  = [
            'username'     => [
                'id'   => 'gateway_username',
                'name' => 'Username',
                'desc' => 'Enter your username.',
            ],
            'password'     => [
                'id'   => 'gateway_password',
                'name' => 'Password',
                'desc' => 'Enter your password.',
            ],
            'from'         => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'You can use local phone number format, or international phone number format (telephone numbers formatted according to the international number format start with a plus sign). If the international phone number format is used, note that you must substitute %2B for the + character, because of URL encoding rules.',
            ],
            'gateway_port' => [
                'id'   => 'gateway_port',
                'name' => 'Gateway Port',
                'desc' => 'Enter the gateway port.',
            ]
        ];
    }

    public function SendSMS()
    {
        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         *
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         *
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         *
         * @since 3.4
         *
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {

            $arguments = [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body'    => [
                    'action'      => 'sendmessage',
                    'username'    => $this->username,
                    'password'    => $this->password,
                    'originator'  => $this->from,
                    'recipient'   => implode(',', $this->to),
                    'messagetype' => 'SMS:TEXT',
                    'messagedata' => $this->msg
                ]
            ];

            $apiURL = str_replace('port', $this->gateway_port, $this->wsdl_link);

            $response = $this->request('POST', $apiURL, [], $arguments);

            if ($response->response->data->acceptreport->statuscode && $response->response->data->acceptreport->statuscode != '0') {
                throw new \Exception($response->response->data->acceptreport->statusmessage);
            }

            //log the result
            $this->log($this->from, $this->msg, $this->to, $response->response->data->acceptreport);

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
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', esc_html__('API username or API password is not entered.', 'wp-sms'));
        }

        return 1;
    }
}