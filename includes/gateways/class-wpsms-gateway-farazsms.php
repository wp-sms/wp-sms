<?php

namespace WP_SMS\Gateway;

use WP_SMS\Gateway;
use Exception;
use WP_Error;

class farazsms extends Gateway
{
    /**
     * API Base URL
     *
     * @var string
     */
    private $wsdl_link = "https://api.iranpayamak.com/";

    /**
     * Pricing page URL
     *
     * @var string
     */
    public $tariff = "https://iranpayamak.com/price/";

    /**
     * Whether trial unit is supported
     *
     * @var bool
     */
    public $unitrial = true;

    /**
     * Unit for credit balance
     *
     * @var string
     */
    public $unit;

    /**
     * Flash SMS support
     *
     * @var string
     */
    public $flash = "disable";

    /**
     * Whether flash SMS is enabled
     *
     * @var bool
     */
    public $isflash = false;

    /**
     * API key availability
     *
     * @var bool
     */
    public $has_key = true;

    /**
     * Gateway settings fields
     *
     * @var array
     */
    public $gatewayFields = [
        'from'    => [
            'id'           => 'gateway_sender_id',
            'name'         => 'Sender Number',
            'place_holder' => 'e.g., 50002178584000',
            'desc'         => 'This is the number or sender ID displayed on recipients’ devices.
It might be a phone number (e.g., 50002178584000) or an alphanumeric ID if supported by your gateway.',
        ],
        'has_key' => [
            'id'   => 'gateway_key',
            'name' => 'API Key',
            'desc' => 'Enter API key of gateway'
        ]
    ];

    /**
     * Validation format for numbers
     *
     * @var string
     */
    public $validateNumber = "09xxxxxxxx";

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Send SMS message
     *
     * @return mixed|WP_Error Response from API or WP_Error on failure
     */
    public function SendSMS()
    {
        $credit = $this->GetCredit();
        if (is_wp_error($credit)) {
            return $credit;
        }

        // Allow filtering sender number
        $this->from = apply_filters('wp_sms_from', $this->from);

        // Allow filtering receiver numbers
        $this->to = apply_filters('wp_sms_to', $this->to);

        // Allow filtering message text
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {
            return $this->sendSimpleSMS();
        } catch (Exception $e) {
            // Log error and return WP_Error
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');
            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    /**
     * Get account credit balance
     *
     * @return float|WP_Error Balance amount or WP_Error on failure
     */
    public function GetCredit()
    {
        if (empty($this->options['gateway_key'])) {
            return new WP_Error('account-credit', esc_html__('API Key is required.', 'wp-sms'));
        }

        try {
            $args = [
                'headers' => array(
                    'Accept'       => 'application/json',
                    'content-type' => 'application/json',
                    'Api-Key'      => $this->options['gateway_key']
                ),
            ];

            $response = $this->request('GET', $this->wsdl_link . 'ws/v1/account/balance', [], $args);

            if (isset($response->status) && $response->status == 'success') {
                return $response->data->balance_amount;
            }

            return new WP_Error('account-credit', esc_html__('Failed to retrieve credit.', 'wp-sms'));
        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

    /**
     * Send a simple SMS message
     *
     * @return mixed|WP_Error Response from API or WP_Error on failure
     */
    private function sendSimpleSMS()
    {
        if (empty($this->options['gateway_key'])) {
            return new WP_Error('send-sms', esc_html__('API Key is required.', 'wp-sms'));
        }

        try {
            $body = [
                'text'          => $this->msg,
                'line_number'   => $this->from,
                'recipients'    => $this->formatReceiverNumbers($this->to),
                'number_format' => 'english'
            ];

            $args = [
                'headers' => array(
                    'Accept'       => 'application/json',
                    'content-type' => 'application/json',
                    'Api-Key'      => $this->options['gateway_key']
                ),
                'body'    => json_encode($body),
            ];

            $response = $this->request('POST', $this->wsdl_link . 'ws/v1/sms/simple', [], $args);

            // Log the response
            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Action hook after sending SMS
             *
             * @param mixed $response API response
             */
            do_action('wp_sms_send', $response);

            // Check response status
            if (isset($response->status) && $response->status !== 'success') {
                return new WP_Error('send-sms', esc_html__('Failed to send SMS.', 'wp-sms'));
            }

            return $response;
        } catch (Exception $e) {
            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    /**
     * Format receiver phone numbers to standard format
     *
     * @param array|string $numbers List of numbers or single number
     *
     * @return array Formatted numbers
     */
    private function formatReceiverNumbers($numbers)
    {
        if (!is_array($numbers)) {
            $numbers = [$numbers];
        }

        $formattedNumbers = [];
        foreach ($numbers as $number) {
            $cleanNumber = preg_replace('/\D+/', '', $number);

            if (substr($cleanNumber, 0, 2) === '98') {
                $formattedNumbers[] = '0' . substr($cleanNumber, 2);
            } elseif (strlen($cleanNumber) === 10 && substr($cleanNumber, 0, 1) === '9') {
                $formattedNumbers[] = '0' . $cleanNumber;
            } else {
                $formattedNumbers[] = $cleanNumber;
            }
        }

        return $formattedNumbers;
    }
}