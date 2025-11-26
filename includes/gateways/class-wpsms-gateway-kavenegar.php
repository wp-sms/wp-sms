<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class kavenegar extends Gateway
{
    /**
     * API Base URL.
     *
     * @var string
     */
    private $wsdlLink = "https://api.kavenegar.com/v1/";

    /**
     * Pricing page URL.
     *
     * @var string
     */
    public $tariff = "https://kavenegar.com/pricing.html";

    /**
     * Determines how the account balance unit is represented.
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
    public $flash = "disabled";

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
    public $templateId = null;

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

        $this->validateNumber = "The correct formats for the recipient's phone number are as follows: 09121234567, 00989121234567, +989121234567, 9121234567";

        $this->gatewayFields = [
            'from' => [
                'id' => 'gateway_sender_id',
                'name' => __('Sender Number', 'wp-sms'),
                'place_holder' => __('e.g., 0018018949161', 'wp-sms'),
                'desc' => __('Number or sender ID shown on recipient’s device.', 'wp-sms'),
            ],
            'has_key' => [
                'id' => 'gateway_key',
                'name' => __('API Key', 'wp-sms'),
                'desc' => __('Enter your gateway API key.', 'wp-sms'),
            ],
        ];

        $this->help = '
<div dir="rtl">
  <h3>ارسال پیامک با الگو (پترن) — راهنمای تنظیم و استفاده از متغیرها</h3>
  <p>
    این قابلیت برای پیام‌هایی مانند ارسال رمز عبور، کد تأیید عضویت، شماره فاکتور، کد تخفیف و سایر اطلاع‌رسانی‌ها کاربرد دارد.
  </p>
  <ol>
    <li>
      <strong>ثبت و تأیید الگو در سامانه پیامکی</strong><br>
      ابتدا در پنل پیامک، یک الگو (پترن) جدید ایجاد کرده و متن آن را با متغیرهای شماره‌گذاری‌شده تنظیم کنید.
      این متغیرها باید به ترتیب با نام‌های زیر تعریف شوند:<br>
      <code>token</code>, <code>token2</code>, <code>token3</code>, <code>token10</code>, <code>token20</code><br>
      نمونه متن در سامانه پیامکی:<br>
      <code style="direction: rtl">
  سلام &lrm;%token%&lrm;، سفارش &lrm;%token2%&lrm; با موفقیت ثبت شد.
    </code>
    </li>
    <li>
      <strong>درج متن پیامک و کد الگو در افزونه</strong><br>
      در افزونه، همان متن را با متغیرهای پلاگین وارد کنید (مثلاً <code>%billing_first_name%</code> و <code>%order_id%</code>) 
      و در انتهای پیامک، پس از علامت «|»، کد الگو را بنویسید.<br>
      نمونه در افزونه:<br>
      <code style="direction: rtl">سلام %billing_first_name%، سفارش %order_id% با موفقیت ثبت شد.|2343</code>
    </li>
  </ol>
  <p><strong>نکات مهم</strong></p>
  <ul>
    <li>در صورتی که <code style="direction: rtl">|کد</code> را قرار ندهید، پیام به‌صورت <em>ارسال معمولی</em> (بدون استفاده از پترن) ارسال می‌شود.</li>
    <li>ترتیب متغیرها در پیامک باید دقیقاً مطابق ترتیب <code>token</code>ها در سامانه پیامکی باشد.</li>
    <li>حداکثر پنج متغیر قابل استفاده است: <code>token</code>, <code>token2</code>, <code>token3</code>, <code>token10</code>, <code>token20</code>.</li>
  </ul>
</div>';
    }

    /**
     * Builds the full API endpoint URL for the SMS gateway.
     *
     * @param string $method
     * @param string $scope
     *
     * @return string
     */
    private function buildUrl($method, $scope = 'sms')
    {
        $key = rawurlencode(trim($this->has_key));
        $svc = rawurlencode($scope);
        $mtd = rawurlencode($method);

        return $this->wsdlLink . "{$key}/{$svc}/{$mtd}.json";
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
            $this->templateId = $templateData['template_id'];
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

        // Filters for customization.
        $this->from = apply_filters('wp_sms_from', $this->from);
        $this->to = apply_filters('wp_sms_to', $this->to);
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        $this->setTemplateIdAndMessageBody();

        try {
            if (!empty($this->templateId) && !empty($this->messageVariables)) {
                $response = $this->sendTemplateSMS();
            } else {
                $response = $this->sendSimpleSMS();
            }

            if (is_wp_error($response)) {
                $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

                return $response;
            }

            if (is_array($response) && isset($response['results']) && is_array($response['results'])) {
                return $this->handleTemplateSendResponseOrThrow($response);
            }

            if ($response->return->status != 200) {
                throw new Exception($response->return->message);
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
        if (empty($this->has_key)) {
            return new WP_Error('missing-api-key', __('API Key is required.', 'wp-sms'));
        }

        try {
            $response = $this->request('GET', $this->buildUrl('info', 'account'));

            if ($response->return->status != 200) {
                throw new Exception($response->return->message);
            }

            return $response->entries->remaincredit;
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
        $params = [
            'receptor' => implode(",", $this->to),
            'message' => rawurlencode($this->msg),
        ];

        if (!empty($this->from)) {
            $params['sender'] = $this->from;
        }

        return $this->request('GET', $this->buildUrl('send'), $params);
    }

    /**
     * Send SMS using template-based API (Service-Line).
     *
     * @return array|WP_Error API response object, or WP_Error on failure.
     * @throws Exception If request fails.
     */
    private function sendTemplateSMS()
    {
        if (empty($this->messageVariables)) {
            return new WP_Error('invalid-template', __('Message does not contain valid template placeholders.', 'wp-sms'));
        }

        if (empty($this->templateId)) {
            return new WP_Error('invalid-template-id', esc_html__('Template ID is missing.', 'wp-sms'));
        }

        $tokens = ['token', 'token2', 'token3', 'token10', 'token20'];
        $messageValues = array_values($this->messageVariables);

        $count = min(count($tokens), count($messageValues));

        $tokensUsed = array_slice($tokens, 0, $count);
        $valuesUsed = array_slice($messageValues, 0, $count);

        $valuesUsed = array_map(function ($v, $t) {
            return in_array($t, ['token', 'token2', 'token3'])
                ? str_replace(' ', '_', $v)
                : $v;
        }, $valuesUsed, $tokensUsed);

        $tokenParams = array_combine($tokensUsed, $valuesUsed);

        $paramsBase = ['template' => $this->templateId] + $tokenParams;

        $results = [];
        $successes = 0;
        $failures = 0;

        foreach ($this->to as $receptor) {
            $params = $paramsBase;
            $params['receptor'] = $receptor;

            $resp = $this->request('GET', $this->buildUrl('lookup', 'verify'), $params);

            if (is_wp_error($resp)) {
                $failures++;
                $results[] = [
                    'to' => $receptor,
                    'status' => 'error',
                    'errorType' => 'wp_error',
                    'message' => $resp->get_error_message(),
                    'raw' => null,
                ];
                continue;
            }

            if ($resp->return->status != 200) {
                $failures++;
                $results[] = [
                    'to' => $receptor,
                    'status' => 'error',
                    'message' => $resp->return->message,
                    'raw' => $resp,
                ];
                continue;
            }

            $successes++;
            $results[] = [
                'to' => $receptor,
                'status' => 'ok',
                'raw' => $resp,
            ];
        }

        $status = $failures == 0 ? 1 : ($successes > 0 ? 206 : 0);

        return [
            'status' => $status,
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

        $successCount = 0;
        $failCount = 0;
        $successNumbers = [];
        $failedNumbers = [];

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
        $failedList = $failedNumbers ? implode(', ', $failedNumbers) : esc_html__('None', 'wp-sms');

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
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new Exception($summary);
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        throw new Exception($summary);
    }
}