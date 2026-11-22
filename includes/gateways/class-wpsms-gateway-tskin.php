<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * TSKIN WhatsApp Gateway.
 *
 * @see https://app.tskin.sa/assets/api-docs.php
 */
class tskin extends \WP_SMS\Gateway
{
    /** @var string */
    private $endpoint = 'https://app.tskin.sa/api/v1/send-message.php';

    public $tariff          = 'https://tskin.sa';
    public $unitrial        = false;
    public $unit;
    public $flash           = 'disable';
    public $isflash         = false;
    public $supportMedia    = false;
    public $supportIncoming = false;

    public function __construct()
    {
        parent::__construct();

        $this->has_key        = true;
        $this->documentUrl    = 'https://app.tskin.sa/assets/api-docs.php';
        $this->validateNumber = __('Use the country code without a leading plus sign, for example 966500000000.', 'wp-sms');
        $this->help           = __('Enter the API key from your TSKIN dashboard. Plain text messages can only be sent within an open 24-hour WhatsApp customer service window.', 'wp-sms');
        $this->gatewayFields  = [
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => __('API Key', 'wp-sms'),
                'type' => 'password',
                'desc' => __('Enter the API key generated in your TSKIN dashboard.', 'wp-sms'),
            ],
        ];
    }

    /**
     * Send a plain text WhatsApp message.
     *
     * @return array|WP_Error
     */
    public function SendSMS()
    {
        $this->from = apply_filters('wp_sms_from', $this->from);
        $this->to   = apply_filters('wp_sms_to', $this->to);
        $this->msg  = apply_filters('wp_sms_msg', $this->msg);

        try {
            if (empty($this->has_key)) {
                throw new Exception(__('TSKIN API Key is required.', 'wp-sms'));
            }

            $recipients = array_values(array_filter(array_map(function ($recipient) {
                return ltrim(trim($recipient), '+');
            }, (array) $this->to)));

            if (empty($recipients)) {
                throw new Exception(__('No valid recipient numbers were provided.', 'wp-sms'));
            }

            $responses = [];

            foreach ($recipients as $recipient) {
                $response = $this->request('POST', $this->endpoint, [], [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->has_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'body'    => wp_json_encode([
                        'phone'        => $recipient,
                        'message_type' => 'text',
                        'message'      => $this->msg,
                    ]),
                ]);

                if (!is_object($response) || empty($response->status) || $response->status !== 'success') {
                    $message = is_object($response) && !empty($response->message) && is_string($response->message)
                        ? $response->message
                        : __('TSKIN returned an unexpected response.', 'wp-sms');

                    throw new Exception($message);
                }

                $responses[] = $response;
            }

            $this->log($this->from, $this->msg, $recipients, $responses);

            /**
             * Fires after WhatsApp messages are sent.
             *
             * @param array $responses TSKIN API responses.
             */
            do_action('wp_sms_send', $responses);

            return $responses;
        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    /**
     * TSKIN does not expose a balance endpoint, so only validate configuration.
     *
     * @return int|WP_Error
     */
    public function GetCredit()
    {
        if (empty($this->has_key)) {
            return new WP_Error('account-credit', __('TSKIN API Key is required.', 'wp-sms'));
        }

        return 1;
    }
}
