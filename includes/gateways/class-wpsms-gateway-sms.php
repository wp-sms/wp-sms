<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class sms extends Gateway
{
    private $wsdl_link = "https://api.sms.ir/v1/";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $gateway_key;

    /**
     * Template ID for Service-Line SMS.ir API.
     *
     * This ID corresponds to the SMS template defined in the SMS.ir panel.
     * Used when sending template-based messages via sendTemplateSMS().
     *
     * @var int
     */
    public $template_id;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send       = true;
        $this->supportMedia    = false;
        $this->supportIncoming = false;
        $this->gatewayFields   = [
            'gateway_key' => [
                'id'   => 'gateway_key',
                'name' => __('API Key', 'wp-sms'),
                'desc' => __('Enter your API KEY', 'wp-sms'),
            ],
            'from'        => [
                'id'   => 'from',
                'name' => __('Sender Number', 'wp-sms'),
                'desc' => __('Enter your Sender Number/Name', 'wp-sms'),
            ],
            'template_id' => [
                'id'   => 'template_id',
                'name' => __('Template ID', 'wp-sms'),
                'desc' => __('Enter your Template ID for Service-Line API', 'wp-sms'),
            ],
        ];
    }

    public function SendSMS()
    {

        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         *
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         *
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         *
         * @since 3.4
         *
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {
            if (empty($this->gateway_key) || empty($this->from)) {
                return new WP_Error('account-credit', 'Please enter your API KEY and Sender Number.');
            }

            if (!empty($this->template_id) && preg_match('/\{.*?\}%.*?%/', $this->msg)) {
                $response = $this->SendTemplateSMS();
            } else {
                $params = [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'ACCEPT'       => 'application/json',
                        'X-API-KEY'    => $this->gateway_key,
                    ],
                    'body'    => wp_json_encode([
                        'lineNumber'  => $this->from,
                        'messageText' => $this->msg,
                        'mobiles'     => $this->to,
                    ])
                ];

                $response = $this->request('POST', $this->wsdl_link . 'send/bulk', [], $params);
            }

            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             *
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
        try {
            if (empty($this->gateway_key) || empty($this->from)) {
                return new WP_Error('account-credit', 'Please enter your API KEY and Sender Number.');
            }

            $params = [
                'headers' => [
                    'ACCEPT'    => 'application/json',
                    'X-API-KEY' => $this->gateway_key,
                ]
            ];

            $response = $this->request('GET', $this->wsdl_link . 'credit', [], $params);

            return $response->data;
        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

    /**
     * Send SMS using the Service-Line template (SMS.ir) API.
     *
     * This method parses the message ($this->msg) for template placeholders in the format:
     * {PARAM_NAME}%value%
     *
     * For example:
     *   $this->msg = "Hello {NAME}%Ali%, your code is {CODE}%123456%";
     *
     * The method converts these placeholders into parameters for the template API.
     *
     * Requirements:
     * - $this->template_id must be set (Template ID from SMS.ir panel)
     * - $this->to[0] must contain the recipient mobile number
     * - $this->gateway_key must contain the SMS.ir API key
     *
     * @return object|WP_Error
     *   - On success: API response as an object
     *   - On failure: WP_Error object with error details
     *
     * @throws Exception
     */
    private function sendTemplateSMS()
    {
        if (!preg_match_all('/\{(.*?)\}%(.+?)%/', $this->msg, $matches)) {
            return new WP_Error('send-sms', esc_html__('Message does not contain valid template placeholders.', 'wp-sms'));
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
                'X-API-KEY'    => $this->gateway_key,
            ],
            'body'    => wp_json_encode($body),
        ];

        return $this->request('POST', $this->wsdl_link . 'send/verify', [], $params);
    }
}
