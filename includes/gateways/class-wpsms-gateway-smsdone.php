<?php

namespace WP_SMS\Gateway;

class smsdone extends \WP_SMS\Gateway
{
    private $wsdl_link;
    public $tariff = "https://sms.smsd.one";
    public $unitrial = false;
    public $unit;
    public $flash = "false";
    public $isflash = false;
    public $gateway_ip;
    public $gateway_port;
    public $username;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->has_key        = true;
        $this->validateNumber = "";
        $this->help           = "Fill the below fields with provided credentials by the SMS gateway provider.";
        $this->gatewayFields  = [
            'gateway_ip'       => [
                'id'   => 'gateway_ip',
                'name' => 'IP',
                'desc' => "Gateway IP without 'port', 'http', 'https', '/', ':', etc. For example: 192.168.1.1",
            ],
            'gateway_port'     => [
                'id'   => 'gateway_port',
                'name' => 'Port',
                'desc' => 'Gateway port. For example: 7788',
            ],
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'API key provided by termination.',
            ],
            'password' => [
                'id'   => 'gateway_password',
                'name' => 'Secret Key',
                'desc' => 'Secret key provided by termination.',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'Caller ID',
                'desc' => 'Sender Identification Number.',
            ],
            'username'     => [
                'id'   => 'gateway_username',
                'name' => 'SMSdone Username',
                'desc' => 'SMSdone login username.',
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

            $this->wsdl_link = "http://{$this->gateway_ip}:{$this->gateway_port}";

            $params = [
                'apikey'         => $this->has_key,
                'secretkey'      => $this->password,
                'callerID'       => $this->from,
                'toUser'         => implode(',', $this->to),
                'messageContent' => urlencode($this->msg)
            ];

            $response = $this->request('GET', "{$this->wsdl_link}/sendtext", $params, []);

            if (isset($response->Status) && $response->Status != '0') {
                throw new \Exception($response);
            }

            //log the result $this->log($this->from, $this->msg, $this->to, $response);

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
            if (!$this->has_key or !$this->password) {
                throw new \Exception(__('The API Key/Secret Key for this gateway is not set.', 'wp-sms'));
            }

            // return 1;
            if(isset($this->username) && !empty($this->username)){
                $response = wp_remote_get(sprintf($this->tariff.'/portal/sms/smsConfiguration/smsClientBalance.jsp?client='.$this->username));

                if (is_wp_error($response)) {
                    return new \WP_Error('account-credit', $response->get_error_message());
                }

                $response_code = wp_remote_retrieve_response_code($response);

                if ($response_code == '200') {
                    return json_decode($response['body'])->Balance;
                } else {
                    return new \WP_Error('account-credit', $response['body']);
                }
            }else{
                 return new \WP_Error('account-credit', $response['body']);
            }


        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            return new \WP_Error('username', "Username is error");
        }

    }

}
