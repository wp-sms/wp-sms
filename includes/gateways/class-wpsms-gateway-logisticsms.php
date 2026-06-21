<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

use Exception;
use WP_Error;

/**
 * LogisticSMS gateway (https://logisticsms.ir).
 *
 * Iranian SMS infrastructure provider with a token-based REST API. The plugin
 * first calls /api/v1/login with the account username and API password to
 * obtain a token, then sends that token in the X-API-TOKEN header on every
 * request (send and account balance).
 *
 * @see http://logisticsms.ir/api-docs/
 */
class logisticsms extends \WP_SMS\Gateway
{
    /**
     * API base URL (including version).
     *
     * @var string
     */
    private $apiBase = "https://api.logisticsms.ir/api/v1";

    /**
     * Pricing page URL.
     *
     * @var string
     */
    public $tariff = "https://logisticsms.ir/";

    /**
     * @var bool
     */
    public $unitrial = false;

    /**
     * @var string
     */
    public $unit;

    /**
     * @var string
     */
    public $flash = "disabled";

    /**
     * @var bool
     */
    public $isflash = false;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->bulk_send      = true;
        $this->has_key        = false;
        $this->validateNumber = "Enter the recipient mobile number in standard format, for example 09121234567.";
        $this->help           = __('LogisticSMS uses your account Username and API Password to obtain an access token automatically. Enter the Username and API Password from your LogisticSMS panel, and set one of your approved sender lines as the Sender ID.', 'wp-sms');

        $this->gatewayFields = [
            'username' => [
                'id'   => 'gateway_username',
                'name' => __('API Username', 'wp-sms'),
                'desc' => __('Your LogisticSMS account username.', 'wp-sms'),
            ],
            'password' => [
                'id'   => 'gateway_password',
                'name' => __('API Password', 'wp-sms'),
                'desc' => __('Your LogisticSMS account password. It is used to obtain the API token automatically.', 'wp-sms'),
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => __('Sender Number', 'wp-sms'),
                'desc' => __('One of your approved LogisticSMS sender lines.', 'wp-sms'),
            ],
        ];
    }

    /**
     * Send SMS message.
     *
     * @return array|WP_Error
     */
    public function SendSMS()
    {
        // Filters for customization.
        $this->from = apply_filters('wp_sms_from', $this->from);
        $this->to   = apply_filters('wp_sms_to', $this->to);
        $this->msg  = apply_filters('wp_sms_msg', $this->msg);

        if (empty($this->username) || empty($this->password)) {
            $error = __('Username and API Password are required.', 'wp-sms');
            $this->log($this->from, $this->msg, $this->to, $error, 'error');

            return new WP_Error('account-credentials', $error);
        }

        try {
            $token      = $this->getToken();
            $recipients = array_filter(array_map('trim', (array) $this->to));
            $results    = [];

            foreach ($recipients as $recipient) {
                $params = [
                    'headers' => [
                        'X-API-TOKEN' => $token,
                        'Accept'      => 'application/json',
                    ],
                    'body'    => [
                        'receptor' => $recipient,
                        'message'  => $this->msg,
                        'sender'   => $this->from,
                    ],
                ];

                $response = $this->request('POST', "{$this->apiBase}/sms/send", [], $params, false);

                if (!is_object($response) || !isset($response->msg)) {
                    throw new Exception(esc_html__('Unexpected response from the LogisticSMS send endpoint.', 'wp-sms'));
                }

                if ($response->msg !== 'success') {
                    throw new Exception(esc_html((string) $response->msg));
                }

                $results[] = $response;
            }

            $this->log($this->from, $this->msg, $this->to, $results);

            /**
             * Fires after an SMS is sent.
             *
             * @param mixed $results API responses.
             */
            do_action('wp_sms_send', $results);

            return $results;
        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    /**
     * Get the remaining SMS credit on the account.
     *
     * @return float|WP_Error
     */
    public function GetCredit()
    {
        try {
            if (empty($this->username) || empty($this->password)) {
                throw new Exception(esc_html__('Username and API Password are required.', 'wp-sms'));
            }

            $token  = $this->getToken();
            $params = [
                'headers' => [
                    'X-API-TOKEN' => $token,
                    'Accept'      => 'application/json',
                ],
            ];

            $response = $this->request('GET', "{$this->apiBase}/account/info", [], $params, false);

            if (!is_object($response) || !isset($response->msg) || $response->msg !== 'success' || !isset($response->data->sms_charge)) {
                throw new Exception(esc_html__('Unexpected response from the LogisticSMS account endpoint.', 'wp-sms'));
            }

            return (float) $response->data->sms_charge;
        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

    /**
     * Authenticate with username and password and return the API token.
     *
     * @return string
     * @throws Exception When the login fails or the response is not parseable.
     */
    private function getToken()
    {
        $params = [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body'    => [
                'username' => $this->username,
                'password' => $this->password,
            ],
        ];

        $response = $this->request('POST', "{$this->apiBase}/login", [], $params, false);

        if (!is_object($response) || !isset($response->msg)) {
            throw new Exception(esc_html__('Unexpected response from the LogisticSMS login endpoint.', 'wp-sms'));
        }

        if ($response->msg !== 'success' || empty($response->data->token)) {
            throw new Exception(esc_html((string) $response->msg));
        }

        return (string) $response->data->token;
    }
}
