<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;
use WP_SMS\Helper;

class gunisms extends Gateway
{
    private $wsdl_link = "https://api.gunisms.com.au/api/v1/";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $gateway_token;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send       = true;
        $this->has_key         = false;
        $this->supportIncoming = true;
        $this->supportMedia    = true;
        $this->validateNumber  = "Number of the recipient  should start with '61' and must be followed by 9 additional digits (e.g., 614xxxxxxxx)";
        $this->help            = "To receive a token, please consult the guide on <a href='https://support.gunisms.com.au/knowledge-base/generate-rest-api-keys' target='_blank'>generating REST API keys</a> for detailed instructions. For <b>bulk send</b>, set Delivery Method to Batch SMS Queue.";
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

            $gatewayType = 'gateway';

            $params = [
                'message'  => $this->msg,
                'contacts' => $this->to,
                'sender'   => $this->from,
            ];

            if ($this->media) {
                $params['media'] = $this->media;
                $gatewayType     = 'gatewaymms';
            }

            $args = [
                'headers' => [
                    'Authorization' => "Bearer $this->gateway_token",
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'body'    => json_encode($params)
            ];


            $response = $this->request('POST', $this->wsdl_link . $gatewayType, [], $args, false);

            if (!$response->status) {
                throw new Exception($response->message);
            }

            $this->log($this->from, $this->msg, $this->to, $response, 'success', $this->media);

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
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error', $this->media);

            return new WP_Error('send-sms', $e->getMessage());
        }

    }

    public function GetCredit()
    {
        try {
            if (empty($this->gateway_token) || empty($this->from)) {
                return new WP_Error('account-credit', 'Please enter your Token and Sender number.');
            }

            $args = [
                'headers' => [
                    'Authorization' => "Bearer $this->gateway_token",
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
            ];

            $response = $this->request('GET', $this->wsdl_link . 'user/token/verify', [], $args, false);

            if (!$response->status) {
                throw new Exception('Invalid token.');
            }

            return 'Gunisms does not provide credit balance. Please check your Gunisms account for billing information.';
        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

}
