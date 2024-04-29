<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class octopush extends Gateway
{
    private $wsdl_link = "https://api.octopush.com/v1/public";
    public $tariff = "https://www.octopush.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $api_login;
    public $api_key;
    public $from;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send       = true;
        $this->supportMedia    = false;
        $this->supportIncoming = false;
        $this->validateNumber  = "Mobile with + and country code";
        $this->help            = "The mobile number must include the <b>country code</b>. To automatically add the country code to the number, set the Country Code Prefix option from the Settings - General section. for <b>bulk send</b>, set Delivery Method to Batch SMS Queue.";
        $this->gatewayFields   = [
            'api_login' => [
                'id'   => 'api_login',
                'name' => __('API Login', 'wp-sms'),
                'desc' => __('Enter your API Login - Username (Email address)', 'wp-sms'),
            ],
            'api_key'   => [
                'id'   => 'api_key',
                'name' => __('API Key', 'wp-sms'),
                'desc' => __('Enter your API KEY', 'wp-sms'),
            ],
            'from'      => [
                'id'   => 'from',
                'name' => __('Sender', 'wp-sms'),
                'desc' => __('Enter Sender Name', 'wp-sms'),
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
            $credit = $this->GetCredit();

            if (is_wp_error($credit)) {
                $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');
                return $credit;
            }

            $recipients = array_map(function ($number) {
                return [
                    'phone_number' => $number,
                ];
            }, $this->to);

            $params = wp_json_encode([
                'text'       => $this->msg,
                'sender'     => $this->from,
                'recipients' => $recipients,
            ]);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $this->wsdl_link . '/sms-campaign/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => "POST",
                CURLOPT_POSTFIELDS     => $params,
                CURLOPT_HTTPHEADER     => [
                    "Content-Type: application/json",
                    "cache-control: no-cache",
                    "api-login: " . $this->api_login,
                    "api-key: " . $this->api_key,
                ],
            ]);

            $response = curl_exec($curl);
            curl_close($curl);

            $response = json_decode($response);

            if (isset($response->code)) {
                throw new Exception($response->message);
            }

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

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');
            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        // Check if the API login and API key is entered.
        if (!$this->api_login or !$this->api_key) {
            return new \WP_Error('account-credit', esc_html__('API Login or API Key is not set.', 'wp-sms'));
        }

        try {
            $params   = [
                'headers' => [
                    'api-login' => $this->api_login,
                    'api-key'   => $this->api_key,
                ]
            ];
            $response = $this->request('GET', $this->wsdl_link . '/wallet/check-balance', [], $params, false);

            if (isset($response->code)) {
                throw new Exception($response->message);
            }

            return $response->amount . ' ' . $response->unit;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }
}