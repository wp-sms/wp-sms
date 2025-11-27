<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class smses extends Gateway
{
    /**
     * API Base URL.
     *
     * @var string
     */
    private $wsdl_link = "https://194.0.137.110:42161/";

    /**
     * Pricing page URL.
     *
     * @var string
     */
    public $tariff = "https://sms.es/";

    /**
     * Whether trial credit is supported.
     *
     * @var bool
     */
    public $unitrial = false;

    /**
     * Unit for credit balance.
     *
     * @var string
     */
    public $unit;

    /**
     * Flash SMS support.
     *
     * @var string
     */
    public $flash = "enable";

    /**
     * Whether flash SMS is enabled.
     *
     * @var bool
     */
    public $isflash = false;

    /**
     * Whether the incoming message is supported
     *
     * @var bool
     */
    public $supportIncoming = true;

    public $documentUrl = 'https://wp-sms-pro.com/resources/smses-gateway-configuration/';

    public $gateway_api_base_url;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->help          = 'Destination number in international format (e.g., 34600000000).';
        $this->gatewayFields = [
            'gateway_api_base_url' => [
                'id'      => 'gateway_api_base_url',
                'name'    => __('API Base URL', 'wp-sms'),
                'type'    => 'select',
                'options' => [
                    'https://194.0.137.110:42161/' => 'https://194.0.137.110:42161',
                ],
                'desc'    => __('Select the base URL for the SMS Gateway API.', 'wp-sms')
            ],
            'username'             => [
                'id'   => 'gateway_username',
                'name' => __('Username', 'wp-sms'),
                'desc' => __('sms.es API username (System ID).', 'wp-sms'),
            ],
            'password'             => [
                'id'   => 'gateway_password',
                'name' => __('Password', 'wp-sms'),
                'desc' => __('sms.es API password.', 'wp-sms'),
            ],
            'from'                 => [
                'id'   => 'gateway_sender_id',
                'name' => __('Sender ID', 'wp-sms'),
                'desc' => __('Alphanumeric or numeric sender (subject to local regulations).', 'wp-sms'),
            ],
        ];
    }

    /**
     * Send SMS message.
     *
     * @return object|WP_Error Response object on success, WP_Error on failure.
     */
    public function SendSMS()
    {
        if (empty($this->username) || empty($this->password)) {
            return new WP_Error('missing-credentials', __('API Username and API Password are required.', 'wp-sms'));
        }

        // Filters for customization.
        $this->from = apply_filters('wp_sms_from', $this->from);
        $this->to   = apply_filters('wp_sms_to', $this->to);
        $this->msg  = apply_filters('wp_sms_msg', $this->msg);

        $apiBaseUrl = !empty($this->gateway_api_base_url) ? $this->gateway_api_base_url : $this->wsdl_link;
        $success = [];
        $errors = [];

        foreach ($this->to as $receiver) {
            try {
                $body = [
                    'type'     => 'text',
                    'auth'     => [
                        'username' => $this->username,
                        'password' => $this->password,
                    ],
                    'sender'   => $this->from,
                    'receiver' => $receiver,
                    'text'     => $this->msg,
                    'dcs'      => (isset($this->options['send_unicode']) && $this->options['send_unicode']) ? 'ucs' : 'gsm',
                ];

                if ($this->isflash) {
                    $body['flash'] = $this->isflash;
                }

                $params = [
                    'headers'   => [
                        'Accept'       => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                    'body'      => json_encode($body),
                    'sslverify' => false,
                ];

                $response = $this->request('POST', $apiBaseUrl . 'bulk/sendsms', [], $params);

                if (isset($response->error)) {
                    throw new Exception($response->error->message);
                }

                $success[$receiver] = $response;

            } catch (Exception $e) {
                $errors[$receiver] = $e->getMessage();
            }
        }

        // Log successful messages separately
        if ($success) {
            $this->log($this->from, $this->msg, array_keys($success), $success);
            do_action('wp_sms_send', $success);
        }

        // Log failed messages separately
        if ($errors) {
            $this->log($this->from, $this->msg, array_keys($errors), $errors, 'error');

            $errorsMessage = sprintf(
                '%d message(s) failed to send: %s',
                count($errors),
                implode(', ', array_keys($errors))
            );

            return new \WP_Error('send-sms-error', $errorsMessage);
        }

        return $success;
    }

    /**
     * Get account credit balance.
     *
     * @return string
     */
    public function GetCredit()
    {
        return 'N/A';
    }
}