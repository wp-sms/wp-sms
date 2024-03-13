<?php

namespace WP_SMS\Gateway;

class onewaysms extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://gateway.onewaysms.com.my:10001/";
    public $tariff = "https://onewaysms.com/";
    public $documentUrl = 'https://wp-sms-pro.com/resources/onewaysms-gateway-configuration/';
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $gatewayMtApiUrl;
    public $gatewayBalanceApiUrl;

    public function __construct()
    {
        parent::__construct();
        $this->has_key        = true;
        $this->bulk_send      = true;
        $this->validateNumber = "Support only 10 numbers, e.g. 6019xxxxxxx,6012xxxxxxx";
        $this->gatewayFields  = [
            'username'             => [
                'id'   => 'gateway_username',
                'name' => 'API username',
                'desc' => 'Enter API username of gateway',
            ],
            'password'             => [
                'id'   => 'gateway_password',
                'name' => 'API password',
                'desc' => 'Enter API password of gateway',
            ],
            'gatewayMtApiUrl'      => [
                'id'   => 'gateway_mt_api_url',
                'name' => 'MT URL',
                'desc' => 'Enter the MT (Mobile Terminating) API URL',
            ],
            'gatewayBalanceApiUrl' => [
                'id'   => 'gateway_balance_api_url',
                'name' => 'Credit Balance URL',
                'desc' => 'Enter the credit balance API URL',
            ],
            'from'                 => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender number',
                'desc' => 'Sender number or sender ID',
            ],
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

        // Check unicode option if enabled.
        $type = 1;
        if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
            $type      = 2;
            $this->msg = $this->convertToUnicode($this->msg);
        }

        $to         = implode(",", $this->to);
        $to         = urlencode($to);
        $this->from = urlencode($this->from);

        $response = wp_remote_get("{$this->gatewayMtApiUrl}?apiusername=" . $this->username . "&apipassword=" . $this->password . "&message=" . $this->msg . "&mobileno=" . $to . "&senderid=" . $this->from . "&languagetype=" . $type);

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $result = json_decode($response['body']);

            if ($result >= 0) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response['body']);

                /**
                 * Run hook after send sms.
                 *
                 * @param string $result result output.
                 *
                 * @since 2.4
                 *
                 */
                do_action('wp_sms_send', $response['body']);

                return $response['body'];
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

                return new \WP_Error('send-sms', $response['body']);
            }
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', esc_html__('The Username/Password for this gateway is not set', 'wp-sms'));
        }

        $response = wp_remote_get("{$this->gatewayBalanceApiUrl}?apiusername={$this->username}&apipassword={$this->password}");

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $result = json_decode($response['body'], true);

            if ($result <= 0) {
                return new \WP_Error('account-credit', $result);
            } else {
                return $result;
            }
        } else {
            return new \WP_Error('account-credit', print_r($response['body'], 1));
        }
    }
}