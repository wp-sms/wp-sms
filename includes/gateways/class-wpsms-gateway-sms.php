<?php

namespace WP_SMS\Gateway;

use WP_SMS\Gateway;
use Exception;
use WP_Error;

class sms extends Gateway
{
    /**
     * API Base URL.
     *
     * @var string
     */
    private $wsdl_link = "https://api.sms.ir/v1/";

    /**
     * Pricing page URL.
     *
     * @var string
     */
    public $tariff = "https://sms.ir/pricing/";

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
    public $flash = "disable";

    /**
     * Whether flash SMS is enabled.
     *
     * @var bool
     */
    public $isflash = false;

    /**
     * API key required flag.
     *
     * @var bool
     */
    public $has_key = true;

    /**
     * Template ID for Service-Line API.
     *
     * @var int
     */
    public $template_id;

    /**
     * Gateway API key.
     *
     * @var string
     */
    public $api_key;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->gatewayFields = [
            'from'        => [
                'id'           => 'gateway_sender_id',
                'name'         => __('Sender Number', 'wp-sms'),
                'place_holder' => __('e.g., 50002178584000', 'wp-sms'),
                'desc'         => __('Number or sender ID shown on recipient’s device.', 'wp-sms'),
            ],
            'has_key'     => [
                'id'   => 'gateway_key',
                'name' => __('API Key', 'wp-sms'),
                'desc' => __('Enter your gateway API key.', 'wp-sms'),
            ],
            'template_id' => [
                'id'   => 'template_id',
                'name' => __('Template ID', 'wp-sms'),
                'desc' => __('Enter template ID for Service-Line API (if used).', 'wp-sms'),
            ],
        ];
        $this->api_key       = !empty($this->options['gateway_key']) ? $this->options['gateway_key'] : '';
    }

    /**
     * Sets the template ID based on the current message.
     *
     * This method extracts the template ID from the message (if present) using
     * `getTemplateIdAndMessageBody()`. If no template ID is found in the message,
     * it falls back to the `template_id` defined in the gateway options.
     * If neither is available, the template ID will be set to an empty string.
     *
     * Usage: Call this method after setting `$this->msg` to ensure the template ID
     * is correctly determined before sending the SMS.
     *
     * @return void
     */
    public function setTemplateIdFromMessage()
    {
        $templateData      = $this->getTemplateIdAndMessageBody();
        $this->template_id = !empty($templateData['template_id']) ? $templateData['template_id'] : (!empty($this->options['template_id']) ? $this->options['template_id'] : '');
    }

    /**
     * Send SMS message.
     *
     * @return object|WP_Error Response object on success, WP_Error on failure.
     */
    public function SendSMS()
    {
        if (empty($this->api_key)) {
            return new WP_Error('missing-api-key', __('API Key is required.', 'wp-sms'));
        }

        $credit = $this->GetCredit();
        if (is_wp_error($credit)) {
            return $credit;
        }

        // Filters for customization.
        $this->from = apply_filters('wp_sms_from', $this->from);
        $this->to   = apply_filters('wp_sms_to', $this->to);
        $this->msg  = apply_filters('wp_sms_msg', $this->msg);

        $this->setTemplateIdFromMessage();

        try {
            if (!empty($this->template_id) && preg_match('/\{.*?\}%.*?%/', $this->msg)) {
                $response = $this->sendTemplateSMS();
            } else {
                $response = $this->sendSimpleSMS();
            }

            if (is_wp_error($response)) {
                $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

                return $response;
            }

            if (!isset($response->status) || $response->status !== 1) {
                return new WP_Error('send-sms-error', __('Failed to send SMS.', 'wp-sms'));
            }

            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Fires after an SMS is sent.
             *
             * @param object $response API response object.
             */
            do_action('wp_sms_send', $response);

            return $response;

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms-error', $e->getMessage());
        }

    }

    /**
     * Get account credit balance.
     *
     * @return float|WP_Error Balance amount on success, WP_Error on failure.
     */
    public function GetCredit()
    {
        try {
            $params = [
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-API-KEY'    => $this->api_key,
                ]
            ];

            $response = $this->request('GET', $this->wsdl_link . 'credit', [], $params);

            if (isset($response->status) && $response->status === 1) {
                return $response->data;
            }

            return new WP_Error('account-credit-error', __('Failed to retrieve credit.', 'wp-sms'));
        } catch (Exception $e) {
            return new WP_Error('account-credit-error', $e->getMessage());
        }
    }

    /**
     * Send a simple SMS message.
     *
     * @return object API response object.
     * @throws Exception If request fails.
     */
    private function sendSimpleSMS()
    {
        $body = [
            'lineNumber'  => $this->from,
            'messageText' => $this->msg,
            'mobiles'     => $this->to,
        ];

        $params = [
            'headers' => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'X-API-KEY'    => $this->api_key,
            ],
            'body'    => json_encode($body),
        ];

        return $this->request('POST', $this->wsdl_link . 'send/bulk', [], $params);
    }

    /**
     * Send SMS using template-based API (Service-Line).
     *
     * Parses message placeholders like `{NAME}%Ali%`.
     *
     * @return object|WP_Error API response object, or WP_Error on failure.
     * @throws Exception If request fails.
     */
    private function sendTemplateSMS()
    {
        if (!preg_match_all('/\{(.*?)\}%(.+?)%/', $this->msg, $matches)) {
            return new WP_Error('invalid-template', __('Message does not contain valid template placeholders.', 'wp-sms'));
        }

        $parameters = [];
        foreach ($matches[1] as $i => $paramName) {
            $parameters[] = [
                "name"  => $paramName,
                "value" => $matches[2][$i]
            ];
        }

        $body = [
            'templateId' => (int)$this->template_id,
            'mobile'     => $this->to[0],
            'parameters' => $parameters,
        ];

        $params = [
            'headers' => [
                'Content-Type' => 'application/json',
                'ACCEPT'       => 'application/json',
                'X-API-KEY'    => $this->api_key,
            ],
            'body'    => wp_json_encode($body),
        ];

        return $this->request('POST', $this->wsdl_link . 'send/verify', [], $params);
    }
}
