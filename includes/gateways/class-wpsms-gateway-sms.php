<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Exception;
use WP_Error;
use WP_SMS\Gateway;

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
    public $template_id = null;

    /**
     * Gateway version.
     */
    public $version = '1.1';

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->gatewayFields = [
            'from'    => [
                'id'           => 'gateway_sender_id',
                'name'         => __('Sender Number', 'wp-sms'),
                'place_holder' => __('e.g., 50002178584000', 'wp-sms'),
                'desc'         => __('Number or sender ID shown on recipient’s device.', 'wp-sms'),
            ],
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => __('API Key', 'wp-sms'),
                'desc' => __('Enter your gateway API key.', 'wp-sms'),
            ],
        ];

        $this->help = "
<div dir='rtl'><h3>ارسال پیامک با قالب (خط خدماتی)</h3>
<ol>
  <li><strong>قالب را در پنل پیامک ثبت و تأیید کنید</strong><br>
    متن قالب و متغیرها باید دقیقاً همان چیزی باشند که در افزونه مینویسید ولی بین <code>##</code> قرار بگیرد..<br>
    <code style='direction: rtl'>سلام #billing_first_name#، سفارش #order_id# با موفقیت ثبت شد.</code>
  </li>
  <li><strong>در افزونه همان متن را بنویسید و کد قالب را با «|» بعد از متن پیامک اضافه کنید</strong><br>
    <code style='direction: rtl'>سلام %billing_first_name%، سفارش %order_id% با موفقیت ثبت شد.|2343</code>
  </li>
</ol>
<p><strong>نکات مهم</strong></p>
<ul>
  <li>نام متغیرها در سمت پنل پیامک باید بین <code>##</code> قرار بگیرند؛ مانند <code>#billing_first_name#</code> و <code>#order_id#</code>.</li>
  <li>اگر <code>|کد</code> نگذارید، پیام به‌صورت <em>ارسال معمولی</em> فرستاده می‌شود.</li>
</ul></div>";
    }

    /**
     * Sets the template ID based on the current message.
     *
     * @return void
     */
    public function setTemplateIdAndMessageBody()
    {
        $templateData = $this->getTemplateIdAndMessageBody();

        if (!empty($templateData['template_id'])) {
            $this->template_id = $templateData['template_id'];
        }

        if (!empty($templateData['message'])) {
            $this->msg = $templateData['message'];
        }
    }

    /**
     * Send SMS message.
     *
     * @return object|WP_Error Response object on success, WP_Error on failure.
     */
    public function SendSMS()
    {
        if (empty($this->has_key)) {
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

        $this->setTemplateIdAndMessageBody();

        try {
            if (!empty($this->template_id) && !empty($this->messageVariables)) {
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
                    'X-API-KEY'    => $this->has_key,
                ]
            ];

            $response = $this->request('GET', $this->wsdl_link . 'credit', [], $params, false);

            if (isset($response->status) && $response->status === 1) {
                return $response->data;
            }

            throw new Exception($response->message);

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
                'X-API-KEY'    => $this->has_key,
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
        if (empty($this->messageVariables)) {
            return new WP_Error('invalid-template', __('Message does not contain valid template placeholders.', 'wp-sms'));
        }

        $apiParameters = [];
        foreach ($this->messageVariables as $key => $value) {
            $apiParameters[] = [
                'name'  => $key,
                'value' => $value,
            ];
        }

        $body = [
            'templateId' => (int)$this->template_id,
            'mobile'     => $this->to[0],
            'parameters' => $apiParameters,
        ];

        $params = [
            'headers' => [
                'Content-Type' => 'application/json',
                'ACCEPT'       => 'application/json',
                'X-API-KEY'    => $this->has_key,
            ],
            'body'    => wp_json_encode($body),
        ];

        return $this->request('POST', $this->wsdl_link . 'send/verify', [], $params);
    }
}
