<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use WP_Error;

/**
 * Rayo SMS (Greece). Rayo runs the same API under two brands: Rayo.gr
 * (https://sms-api.rayo.gr) and Rayo SMS (https://api.rayosms.com), so one
 * class serves both and the site owner picks the brand their account is on.
 *
 * API: https://sms-api-docs.rayo.gr/ (Bearer API key, JSON body).
 */
class rayo extends \WP_SMS\Gateway
{
    /**
     * API base URL per brand.
     *
     * @var array
     */
    protected $hosts = [
        'rayo.gr'     => 'https://sms-api.rayo.gr/v1/',
        'rayosms.com' => 'https://api.rayosms.com/v1/',
    ];

    public $tariff = "https://www.rayo.gr/";
    public $documentUrl = 'https://wsms.io/docs/gateway-configuration';
    public $unitrial = true;
    public $unit;
    public $flash = "disabled";
    public $isflash = false;
    public $bulk_send = true;

    /**
     * Which brand the account is on: rayo.gr or rayosms.com.
     *
     * @var string
     */
    public $platform = 'rayo.gr';

    /**
     * "on" asks Rayo to accept the message without delivering it or using credits.
     *
     * @var string
     */
    public $simulate = 'off';

    public function __construct()
    {
        parent::__construct();

        $this->has_key        = true;
        $this->validateNumber = 'International format with the country code, e.g. +306912345678 or 306912345678';
        $this->help           = 'Create an API key in your Rayo account under Advanced > Settings. The Sender ID must be one you have registered with Rayo; ask Rayo support to set it up for the countries you send to.';

        $this->gatewayFields = [
            'platform' => [
                'id'      => 'gateway_rayo_platform',
                'name'    => 'Rayo account',
                'desc'    => 'The site where you created your Rayo account.',
                'type'    => 'select',
                'options' => [
                    'rayo.gr'     => 'Rayo.gr',
                    'rayosms.com' => 'RayoSMS.com',
                ],
            ],
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'Your Rayo API key (Advanced > Settings in your Rayo account).',
            ],
            'from'     => [
                'id'           => 'gateway_sender_id',
                'name'         => 'Sender ID',
                'place_holder' => 'e.g., MYSHOP',
                'desc'         => 'A sender ID registered in your Rayo account.',
            ],
            'simulate' => [
                'id'      => 'gateway_rayo_simulate',
                'name'    => 'Test mode',
                'desc'    => 'When on, Rayo accepts messages without sending them or using credits. Turn it off before going live.',
                'type'    => 'select',
                'options' => [
                    'off' => 'Off',
                    'on'  => 'On',
                ],
            ],
        ];
    }

    /**
     * API base URL for the selected brand. Unknown values fall back to Rayo.gr.
     *
     * @return string
     */
    public function getApiBase()
    {
        return $this->hosts[$this->platform] ?? $this->hosts['rayo.gr'];
    }

    public function SendSMS()
    {
        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         * @since 3.4
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         * @since 3.4
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         * @since 3.4
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        if (empty($this->has_key) || $this->has_key === true) {
            $error = __('The Rayo API key is not set.', 'wp-sms');
            $this->log($this->from, $this->msg, $this->to, $error, 'error');

            return new WP_Error('send-sms', $error);
        }

        if (empty($this->from)) {
            $error = __('The Rayo Sender ID is not set. Use a sender ID registered in your Rayo account.', 'wp-sms');
            $this->log($this->from, $this->msg, $this->to, $error, 'error');

            return new WP_Error('send-sms', $error);
        }

        $body = [
            'toMobileNumber' => implode(',', array_map([$this, 'cleanNumber'], (array)$this->to)),
            'fromLabel'      => $this->from,
            'smsBody'        => $this->msg,
        ];

        if ($this->simulate === 'on') {
            $body['simulateSendFlag'] = true;
        }

        $response = wp_remote_post($this->getApiBase() . 'send_sms', [
            'timeout' => 15,
            'headers' => $this->headers(),
            'body'    => wp_json_encode($body),
        ]);

        $result = $this->parseResponse($response);

        if (is_wp_error($result)) {
            $this->log($this->from, $this->msg, $this->to, $result->get_error_message(), 'error');

            return new WP_Error('send-sms', $result->get_error_message());
        }

        $this->log($this->from, $this->msg, $this->to, $result);

        /**
         * Run hook after send sms.
         *
         * @param object $result API response.
         * @since 2.4
         */
        do_action('wp_sms_send', $result);

        return $result;
    }

    public function GetCredit()
    {
        if (empty($this->has_key) || $this->has_key === true) {
            return new WP_Error('account-credit', __('The Rayo API key is not set.', 'wp-sms'));
        }

        $response = wp_remote_get($this->getApiBase() . 'balance_in_credits', [
            'timeout' => 10,
            'headers' => $this->headers(),
        ]);

        $result = $this->parseResponse($response, false);

        if (is_wp_error($result)) {
            return new WP_Error('account-credit', $result->get_error_message());
        }

        if (!isset($result->balance->credits)) {
            return new WP_Error('account-credit', __('Rayo did not return a balance.', 'wp-sms'));
        }

        return $result->balance->credits;
    }

    /**
     * Rayo expects the country code without "+" or "00", e.g. 306912345678.
     *
     * @param string $number
     * @return string
     */
    public function cleanNumber($number)
    {
        $number = preg_replace('/[^\d+]/', '', (string)$number);

        if (strpos($number, '+') === 0) {
            return substr($number, 1);
        }

        if (strpos($number, '00') === 0) {
            return substr($number, 2);
        }

        return $number;
    }

    /**
     * @return array
     */
    private function headers()
    {
        return [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . trim($this->has_key),
        ];
    }

    /**
     * Turn a Rayo HTTP response into the decoded body or a WP_Error with a plain message.
     *
     * Rayo answers 200 with result.resultCode "1" on success, and 401, 422 or 5xx with
     * {"result": {"resultDescription": "ERROR: ..."}, "error": "..."} on failure.
     *
     * @param array|WP_Error $response
     * @param bool $checkResultCode Send responses carry result.resultCode; the balance does not.
     * @return object|WP_Error
     */
    private function parseResponse($response, $checkResultCode = true)
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int)wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response));

        if ($code >= 200 && $code < 300 && is_object($body)) {
            if (!$checkResultCode || (isset($body->result->resultCode) && (string)$body->result->resultCode === '1')) {
                return $body;
            }
        }

        $detail = '';
        if (is_object($body)) {
            if (!empty($body->error)) {
                $detail = $body->error;
            } elseif (!empty($body->result->resultDescription)) {
                $detail = $body->result->resultDescription;
            }
        }

        $hints = [
            401 => __('The API key is not valid or not active. Check it in your Rayo account under Advanced > Settings.', 'wp-sms'),
            422 => __('Rayo refused the request. Most often the Sender ID is not registered in your Rayo account.', 'wp-sms'),
        ];

        $message = sprintf(__('Rayo error %s', 'wp-sms'), $code ?: '');
        if ($detail) {
            $message .= ': ' . $detail;
        }
        if (isset($hints[$code])) {
            $message .= ' - ' . $hints[$code];
        } elseif ($code >= 500) {
            $message .= ' - ' . __('Rayo had a server problem. Try again in a few minutes.', 'wp-sms');
        }

        return new WP_Error('rayo-error', $message);
    }
}
