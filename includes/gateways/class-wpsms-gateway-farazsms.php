<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class farazsms extends Gateway
{
    /**
     * API Base URL.
     *
     * @var string
     */
    private $wsdl_link = "https://api.iranpayamak.com/";

    /**
     * Pricing page URL.
     *
     * @var string
     */
    public $tariff = "https://iranpayamak.com/price/";

    /**
     * Whether trial credit is supported.
     *
     * @var bool
     */
    public $unitrial = true;

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
     * @var string
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
<div dir='rtl'><h3>ارسال پیامک با پترن (خط خدماتی)</h3>
<ol>
  <li><strong>الگو را در پنل پیامک ثبت و تأیید کنید</strong><br>
    متن الگو و متغیرها باید دقیقاً همان چیزی باشند که در افزونه می‌نویسید.<br>
    <code style='direction: rtl'>سلام %billing_first_name%، سفارش %order_id% با موفقیت ثبت شد.</code>
  </li>
  <li><strong>در افزونه همان متن را بنویسید و کد الگو را با «|» بعد از متن پیامک اضافه کنید</strong><br>
    <code style='direction: rtl'>سلام %billing_first_name%، سفارش %order_id% با موفقیت ثبت شد.|2343</code>
  </li>
</ol>
<p><strong>نکات مهم</strong></p>
<ul>
  <li>نام متغیرها باید دقیقاً یکسان باشند؛ مانند <code>%billing_first_name%</code> و <code>%order_id%</code>.</li>
  <li>اگر <code style='direction: rtl'>|کد</code> نگذارید، پیام به‌صورت <em>ارسال معمولی</em> فرستاده می‌شود.</li>
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

        $this->setTemplateIdAndMessageBody();

        // Filters for customization.
        $this->from = apply_filters('wp_sms_from', $this->from);
        $this->to   = apply_filters('wp_sms_to', $this->to);
        $this->msg  = apply_filters('wp_sms_msg', $this->msg);

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

            if (!isset($response->status) || $response->status !== 'success') {
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
                    'Api-Key'      => $this->has_key,
                ],
            ];

            $response = $this->request('GET', $this->wsdl_link . 'ws/v1/account/balance', [], $params, false);

            if (isset($response->status) && $response->status === 'success') {
                return $response->data->balance_amount;
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
            'text'          => $this->msg,
            'line_number'   => $this->from,
            'recipients'    => $this->formatReceiverNumbers($this->to),
            'number_format' => 'english',
        ];

        $params = [
            'headers' => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'Api-Key'      => $this->has_key,
            ],
            'body'    => json_encode($body),
        ];

        return $this->request('POST', $this->wsdl_link . 'ws/v1/sms/simple', [], $params);
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

        $body = [
            'code'          => $this->template_id,
            'attributes'    => $this->messageVariables,
            'recipient'     => $this->formatReceiverNumbers($this->to)[0],
            'line_number'   => $this->from,
            'number_format' => 'english',
        ];

        $params = [
            'headers' => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'Api-Key'      => $this->has_key,
            ],
            'body'    => wp_json_encode($body),
        ];

        return $this->request('POST', $this->wsdl_link . 'ws/v1/sms/pattern', [], $params);
    }

    /**
     * Format receiver phone numbers.
     *
     * Ensures numbers are in local `09xxxxxxxxx` format.
     *
     * @param array|string $numbers Phone number(s).
     * @return array Formatted phone numbers.
     */
    private function formatReceiverNumbers($numbers)
    {
        if (!is_array($numbers)) {
            $numbers = [$numbers];
        }

        $formatted = [];
        foreach ($numbers as $number) {
            $clean = preg_replace('/\D+/', '', $number);

            if (substr($clean, 0, 2) === '98') {
                $formatted[] = '0' . substr($clean, 2);
            } elseif (strlen($clean) === 10 && substr($clean, 0, 1) === '9') {
                $formatted[] = '0' . $clean;
            } else {
                $formatted[] = $clean;
            }
        }

        return $formatted;
    }
}