<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Exception;
use WP_Error;
use WP_SMS\Gateway;
use WP_SMS\Helper;

class _textplode extends \WP_SMS\Gateway
{
    private $wsdl_link      = 'http://api.textplode.com/v3';
    public  $version        = '2.0';
    public  $tariff         = 'https://www.textplode.com/';
    public  $unitrial       = false;
    public  $unit;
    public  $flash          = 'disable';
    public  $isflash        = false;
    public  $bulk_send      = true;
    public  $supportMedia   = false;
    public  $supportIncoming= false;
    public  $has_key        = true;
    public  $help           = '';
    public  $api_key;
    public  $sender;

    public $gatewayFields = [
        'api_key' => [
            'id'   => 'textplode_api_key',
            'name' => 'API Key',
            'desc' => 'Paste your Textplode API key here.',
            'type' => 'text',
        ],
        'sender'  => [
            'id'   => 'textplode_sender',
            'name' => 'From Name',
            'desc' => 'Alphanumeric or phone number per Textplode rules.',
            'type' => 'text',
        ]
    ];

    public function __construct()
    {
        parent::__construct();

        // Optional: set defaults or dynamic help
        $this->help = $this->help ?: esc_html__('Configure Textplode API Key and From Name.', 'wp-sms');
    }

    public function SendSMS()
    {
        // Let WP SMS apply number cleaning / country code if enabled in settings
        $this->from = apply_filters('wp_sms_from', $this->from);
        $this->to   = apply_filters('wp_sms_to',   $this->to);
        $this->msg  = apply_filters('wp_sms_msg',  $this->msg);

        try {
            // Build recipients array for Textplode API format
            $recipients = [];
            foreach ((array) $this->to as $number) {
                $recipients[] = [
                    'phone_number' => $number,
                    'merge'        => []
                ];
            }

            // Build HTTP params
            $params = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'api_key'    => trim($this->api_key),
                    'from'       => $this->from ?: trim($this->sender),
                    'message'    => $this->msg,
                    'recipients' => wp_json_encode($recipients),
                ]
            ];

            $response = $this->request('POST', $this->wsdl_link . '/messages/send', [], $params, true);

            // Check for API errors
            if (isset($response->errors->errorCode) && $response->errors->errorCode != 200) {
                $errorMessage = $response->errors->errorMessage ?? esc_html__('Unknown API error.', 'wp-sms');
                throw new Exception($errorMessage);
            }

            // Check if we have campaign_id in the response data
            if (!isset($response->data->campaign_id)) {
                throw new Exception(esc_html__('Invalid response from gateway.', 'wp-sms'));
            }

            // Log to Outbox table
            $this->log($this->from, $this->msg, $this->to, $response);

            // Fire plugin hook
            do_action('wp_sms_send', $response);

            return $response;

        } catch (Exception $e) {
            
            // Log error to Outbox
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        try {
            if (empty($this->api_key)) {
                return new WP_Error('account-credit', esc_html__('Please enter your API key.', 'wp-sms'));
            }

            // Build HTTP params for POST request
            $params = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body'    => [
                    'api_key' => trim($this->api_key),
                ]
            ];

            $response = $this->request('POST', $this->wsdl_link . '/account/get/credits', [], $params, true);

            return $response->data[0]->credits ?? throw new Exception(esc_html__('Unable to retrieve balance.', 'wp-sms'));

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }
}
