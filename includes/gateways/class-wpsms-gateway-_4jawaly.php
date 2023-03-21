<?php

namespace WP_SMS\Gateway;

class _4jawaly extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api-sms.4jawaly.com/api/v1/account/area/sms/send";
    private $wsdl_link2 ="https://api-sms.4jawaly.com/api/v1/account/area/me/packages?is_active=1&order_by=id&order_by_type=desc&page=1&page_size=10&return_collection=1";
    public $tariff = "https://www.4jawaly.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->help           = "Please use API Key as your 4jawaly Account";
        $this->validateNumber = "eg: 966xxxxxxx";
        $this->has_key        = true;
        $this->gatewayFields  = [
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'APP Key',
                'desc' => 'Enter APP key of gateway.'
            ],
            'password' => [
                'id'   => 'gateway_password',
                'name' => 'APP Secret',
                'desc' => 'Enter the APP Secret.',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'Source Number',
                'desc' => 'Enter the source number.',
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


        // Get the credit.
        $credit = $this->GetCredit();

        

        // Check gateway credit
        if (is_wp_error($credit)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return $credit;
        }

        $msg = urlencode($this->msg);

        // Get response
      $args = array(
        'headers' => array(
            'Accept'  => 'application/json',
            'Content-Type'  => 'application/json',
            'User-Agent'  => 'WP-SMS-Pr',
            'Authorization' => 'Basic ' . base64_encode($this->has_key . ':' . $this->password)

        ),
        'body'    => json_encode(array(
            "messages" => array(
                array(
            
            'text'                => $this->msg,
            'sender'                => $this->from,
            'numbers'                  => $this->to,
                ),
            ),
        ),
    ),
);

    $response = wp_remote_post("{$this->wsdl_link}", $args);
    $response_code = wp_remote_retrieve_response_code($response);
        // Decode response
        $response = json_decode($response['body']);


        if ($response_code == 200 and isset($response->messages) and $response->messages) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             *
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

            return true;
        } else {
            // Log th result
            $this->log($this->from, $this->msg, $this->to, $response->MessageIs, 'error');

            return new \WP_Error('send-sms', $response->message);
        }

    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('The username/password for this gateway is not set', 'wp-sms'));
        }
        $args = array(
            'headers' => array(
                'Accept'  => 'application/json',
                'Content-Type'  => 'application/json',
                'User-Agent'  => 'WP-SMS-Pr',
                'Authorization' => 'Basic ' . base64_encode($this->has_key . ':' . $this->password)
    
            ),

    );
        // Get response
        $response = wp_remote_get("{$this->wsdl_link2}", $args);
        $response_code = wp_remote_retrieve_response_code($response);

        // Decode response
        $response = json_decode($response['body']);

        if ($response->code == 200) {
            // Return blance
            return $response->total_balance;
        } else {
            return new \WP_Error('account-credit', $response->message);
        }
    }
}