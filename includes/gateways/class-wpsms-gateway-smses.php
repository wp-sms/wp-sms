<?php

namespace WP_SMS\Gateway;

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
                    'https://api-cpaas.sms.es/'    => 'https://api-cpaas.sms.es',
                    'https://194.0.137.110:42161/' => 'https://194.0.137.110:42161',
                ],
                'desc'    => __('Select the base URL for the SMS Gateway API.', 'wp-sms')
            ],
            'username'             => [
                'id'   => 'gateway_username',
                'name' => 'Username',
                'desc' => 'sms.es API username (System ID).',
            ],
            'password'             => [
                'id'   => 'gateway_password',
                'name' => 'Password',
                'desc' => 'sms.es API password.',
            ],
            'from'                 => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'Alphanumeric or numeric sender (subject to local regulations).',
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

        try {
            $response = $this->sendSimpleSMS();

            if (is_array($response) && isset($response['results']) && is_array($response['results'])) {
                return $this->handleTemplateSendResponseOrThrow($response);
            }

            throw new Exception(esc_html__('Unexpected send response.', 'wp-sms'));
        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms-error', $e->getMessage());
        }
    }

    /**
     * Get account credit balance.
     *
     * @return null
     */
    public function GetCredit()
    {
        return 'N/A';
    }

    /**
     * Send a simple SMS message.
     *
     * @return array
     */
    private function sendSimpleSMS()
    {
        $receivers  = $this->to;
        $apiBaseUrl = !empty($this->gateway_api_base_url) ? $this->gateway_api_base_url : $this->wsdl_link;
        $results    = [];
        $successes  = 0;
        $failures   = 0;

        foreach ($receivers as $receiver) {
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

            $resp = $this->request('POST', $apiBaseUrl . 'bulk/sendsms', [], $params, false);

            if (is_wp_error($resp)) {
                $failures++;
                $message = $resp->get_error_message();

                $results[] = [
                    'to'        => $receiver,
                    'status'    => 'error',
                    'errorType' => 'wp_error',
                    'message'   => $resp->get_error_message(),
                    'raw'       => null,
                ];

                $this->log($this->from, $this->msg, $receiver, $message, 'error');
                continue;
            }

            if (!empty($resp->error)) {
                $failures++;
                $errorCode = isset($resp->error->code) ? (int)$resp->error->code : null;
                $errorMsg  = isset($resp->error->message) ? (string)$resp->error->message : __('Unknown error', 'wp-sms');

                $results[] = [
                    'to'           => $receiver,
                    'status'       => 'error',
                    'errorCode'    => $errorCode,
                    'errorMessage' => $errorMsg,
                    'raw'          => $resp,
                ];

                $this->log($this->from, $this->msg, $receiver, $errorMsg, 'error');
                continue;
            }

            $successes++;
            $results[] = [
                'to'     => $receiver,
                'status' => 'ok',
                'raw'    => $resp,
            ];
            $this->log($this->from, $this->msg, $receiver, sprintf('Message sent. msgId=%s', $resp->msgId));
        }

        $status = $failures == 0 ? 1 : ($successes > 0 ? 206 : 0);

        return [
            'status'  => $status,
            'summary' => ['success' => $successes, 'failure' => $failures],
            'results' => $results,
        ];
    }

    /**
     * Handle and log template (batch) send response.
     * Throws Exception on partial/full failure with comma-separated failed numbers.
     *
     * @param array $response
     * @return object  Casted object on full success
     * @throws Exception
     */
    private function handleTemplateSendResponseOrThrow($response)
    {
        if (!isset($response['results']) || !is_array($response['results'])) {
            throw new Exception(esc_html__('Invalid template response payload.', 'wp-sms'));
        }

        $successCount   = 0;
        $failCount      = 0;
        $successNumbers = [];
        $failedNumbers  = [];

        foreach ($response['results'] as $item) {
            $toOne = $item['to'] ?? $this->to;

            if (($item['status'] ?? '') == 'ok') {
                $successCount++;
                if (is_array($toOne)) {
                    $successNumbers = array_merge($successNumbers, $toOne);
                } else {
                    $successNumbers[] = $toOne;
                }
            } else {
                $failCount++;
                if (is_array($toOne)) {
                    $failedNumbers = array_merge($failedNumbers, $toOne);
                } else {
                    $failedNumbers[] = $toOne;
                }
            }
        }

        $successList = $successNumbers ? implode(', ', $successNumbers) : esc_html__('None', 'wp-sms');
        $failedList  = $failedNumbers ? implode(', ', $failedNumbers) : esc_html__('None', 'wp-sms');

        $summary = sprintf(
            "SMS Summary:\nSuccess: %d\nFailed: %d\nSuccess Numbers: %s\nFailed Numbers: %s",
            $successCount,
            $failCount,
            $successList,
            $failedList
        );

        $status = $response['status'] ?? 0;

        if ($status == 1) {
            $obj = (object)$response;

            $this->log($this->from, $this->msg, $this->to, $summary);

            /**
             * Fires after an SMS is sent.
             *
             * @param object $response API response object.
             */
            do_action('wp_sms_send', $obj);

            return $obj;
        }

        if ($status == 206) {
            throw new Exception($summary);
        }

        throw new Exception($summary);
    }
}