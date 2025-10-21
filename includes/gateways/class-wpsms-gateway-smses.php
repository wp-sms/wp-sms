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
                'desc' => 'Enter your Username.',
            ],
            'password'             => [
                'id'   => 'gateway_password',
                'name' => 'Password',
                'desc' => 'Enter your Password.',
            ],
            'from'                 => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => '(alphanumeric or numeric depending on regulations).',
            ],
        ];
    }

    /**
     * Send SMS message.
     *
     * @return array|WP_Error Response object on success, WP_Error on failure.
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

            $successCount = 0;
            $failCount    = 0;
            $successNums  = [];
            $failedNums   = [];

            foreach ($response as $number => $result) {
                if (($result['status'] ?? '') === 'success') {
                    $successCount++;
                    $successNums[] = $number;
                } else {
                    $failCount++;
                    $failedNums[] = $number;
                }
            }

            // Build readable lists
            $successList = $successNums ? implode(', ', $successNums) : 'None';
            $failedList  = $failedNums ? implode(', ', $failedNums) : 'None';

            // Prepare summary message
            $summary = sprintf(
                "SMS Summary:\nSuccess: %d\nFailed: %d\nSuccess Numbers: %s\nFailed Numbers: %s",
                $successCount,
                $failCount,
                $successList,
                $failedList
            );

            // Throw exception if any failed
            if ($failCount > 0) {
                $errorMsg = sprintf(
                    __('%d SMS message(s) failed to send. Failed numbers: %s', 'wp-sms'),
                    $failCount,
                    $failedList
                );
                throw new Exception($errorMsg);
            }

            // Log summary and details
            $this->log($this->from, $this->msg, $this->to, $summary);

            // All succeeded
            return $response;

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
        return null;
    }

    /**
     * Send a simple SMS message.
     *
     * @return array API response object.
     * @throws Exception If request fails.
     */
    private function sendSimpleSMS()
    {
        $receivers  = $this->to;
        $resultMap  = [];
        $apiBaseUrl = !empty($this->gateway_api_base_url) ? $this->gateway_api_base_url : $this->wsdl_link;

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

            $response = $this->request('POST', $apiBaseUrl . 'bulk/sendsms ', [], $params, false);

            // Normalize into the requested per-recipient shape
            if (is_wp_error($response)) {
                $resultMap[$receiver] = [
                    'status'       => 'error',
                    'errorMessage' => $response->get_error_message(),
                ];
                continue;
            }

            // API-level error from your sample log:
            if (!empty($response->error)) {
                $resultMap[$receiver] = [
                    'status'       => 'error',
                    'errorCode'    => isset($response->error->code) ? (int)$response->error->code : null,
                    'errorMessage' => isset($response->error->message) ? (string)$response->error->message : __('Unknown error', 'wp-sms'),
                ];
                continue;
            }

            // Success shape: { "msgId":"<uuid>", "numParts":1 }
            if (isset($response->msgId)) {
                $resultMap[$receiver] = [
                    'status'   => 'success',
                    'msgId'    => (string)$response->msgId,
                    'numParts' => (int)($response->numParts ?? 1),
                ];

                do_action('wp_sms_send', $response);

                continue;
            }

            // Fallback/unknown response
            $resultMap[$receiver] = [
                'status'       => 'error',
                'errorMessage' => __('Unexpected API response.', 'wp-sms'),
            ];
        }

        return $resultMap;
    }
}