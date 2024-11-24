<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;
use WP_SMS\Helper;

class smspoint extends Gateway
{
    private $wsdl_link = "https://app.smspoint.de/public/api/v1/sms/send";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $gateway_token;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send       = false;
        $this->has_key         = false;
        $this->supportIncoming = false;
        $this->validateNumber  = "Mobile number without +";
        $this->help            = "The option to send Bulk SMS is not available in this gateway.";
        $this->gatewayFields   = [
            'gateway_token' => [
                'id'   => 'gateway_token',
                'name' => 'Token',
                'desc' => 'Enter your Token.',
            ],
            'from'          => [
                'id'   => 'from',
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

        try {
            $balance = $this->GetCredit();

            if (is_wp_error($balance)) {
                throw new Exception($balance->get_error_message());
            }

            $this->to = Helper::removeNumbersPrefix(['+'], $this->to);

            $params = [
                'headers' => [
                    'Content-Type' => 'application/json;charset=UTF-8',
                    'Accept'       => 'application/json',
                    'X-Auth-Token' => $this->gateway_token,
                ],
                'body'    => json_encode([
                    'senderName' => $this->from,
                    'body'       => $this->msg,
                    'phone'      => $this->to[0],
                ]),
            ];

            $response = $this->request('POST', $this->wsdl_link, [], $params);

            if ($response->success != true) {
                throw new Exception($response);
            }

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

            return $response;

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }

    }

    public function GetCredit()
    {
        try {

            if (empty($this->gateway_token) || empty($this->from)) {
                return new WP_Error('account-credit', 'Please enter your token and sender name.');
            }

            return 'Unable to check balance!';
        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

}
