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
                'place_holder' => __('e.g., 10001234567890', 'wp-sms'),
                'desc' => __('Your line number from the Kavenegar panel.', 'wp-sms'),
            ],
            'has_key' => [
                'id' => 'gateway_key',
                'name' => __('API Key', 'wp-sms'),
                'desc' => __('Enter your gateway API key.', 'wp-sms'),
            ],
        ];

        $this->help = '
<div dir="rtl">
  <p><strong>شماره ارسال کننده:</strong> شماره خط خود را از پنل کاوه نگار وارد کنید (مثلا 1000xxxxxxxxxx).</p>
  <p><strong>ارسال با الگو (اختیاری، برای کد تایید و اطلاع رسانی):</strong></p>
  <ol>
    <li>در پنل کاوه نگار یک الگو بسازید و به جای هر مقدار، به ترتیب <code>%token</code>، <code>%token2</code>، <code>%token3</code>، <code>%token10</code> و <code>%token20</code> بنویسید.<br>
      مثال: <code>سلام %token، سفارش %token2 ثبت شد.</code></li>
    <li>در افزونه همان پیام را با متغیرهای افزونه بنویسید و در انتها بعد از <code>|</code> نام الگو را بگذارید.<br>
      مثال: <code>سلام %billing_first_name%، سفارش %order_id% ثبت شد.|order-confirm</code></li>
  </ol>
  <p>اولین متغیر پیام جای <code>%token</code> می نشیند، دومی جای <code>%token2</code> و همین طور تا پنج متغیر. <code>%token</code> تا <code>%token3</code> فاصله نمی پذیرند و فاصله ها حذف می شوند؛ مقدار چند کلمه ای (مثل نام کامل) را چهارم یا پنجم بگذارید. بدون <code>|</code> و نام الگو، پیام به صورت عادی ارسال می شود.</p>
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
                throw new Exception($response->return->status . ' ' . $response->return->message);
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
            $message = $this->withHint($e->getMessage());
            $this->log($this->from, $this->msg, $this->to, $message, 'error');

            return new WP_Error('send-sms-error', $message);
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
        // Kavenegar takes at most 200 receptors per call (error 414). POST keeps
        // long messages out of the URL.
        $response = null;

        foreach (array_chunk($this->to, 200) as $receptors) {
            $body = [
                'receptor' => implode(',', $receptors),
                'message'  => $this->msg,
            ];

            if (!empty($this->from)) {
                $body['sender'] = $this->from;
            }

            $response = $this->request('POST', $this->buildUrl('send'), [], ['body' => $body]);

            if ($response->return->status != 200) {
                return $response;
            }
        }

        return $response;
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

        $valuesUsed = array_map([$this, 'cleanToken'], $valuesUsed, $tokensUsed);

        $tokenParams = array_combine($tokensUsed, array_map('rawurlencode', $valuesUsed));

        $paramsBase = ['template' => rawurlencode($this->templateId)] + $tokenParams;

        $results = [];
        $successes = 0;
        $failures = 0;

        foreach ($this->to as $receptor) {
            $params = $paramsBase;
            $params['receptor'] = rawurlencode($receptor);

            try {
                $resp = $this->request('GET', $this->buildUrl('lookup', 'verify'), $params);
            } catch (Exception $e) {
                $failures++;
                $results[] = [
                    'to' => $receptor,
                    'status' => 'error',
                    'message' => $this->withHint($e->getMessage()),
                    'raw' => null,
                ];
                continue;
            }

            if ($resp->return->status != 200) {
                $failures++;
                $results[] = [
                    'to' => $receptor,
                    'status' => 'error',
                    'message' => $this->withHint($resp->return->status . ' ' . $resp->return->message),
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
        $errors = [];

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
                if (!empty($item['message'])) {
                    $errors[] = $item['message'];
                }
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

        // Say why the failed numbers failed; one line per distinct reason.
        if ($errors) {
            $summary .= "\nError: " . implode("\nError: ", array_unique($errors));
        }

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

    /**
     * Make a template value acceptable to Kavenegar's verify/lookup.
     *
     * token, token2 and token3 take no spaces; token10 takes up to 5 and token20 up to 8.
     * Line breaks and underscores are refused everywhere (error 431). Max 100 characters.
     *
     * @param string $value
     * @param string $token
     * @return string
     */
    private function cleanToken($value, $token)
    {
        $value = trim(str_replace('_', ' ', (string)$value));
        $value = preg_replace('/\s+/u', ' ', $value);

        $maxSpaces = ['token10' => 5, 'token20' => 8];
        $words = explode(' ', $value);
        $keep = isset($maxSpaces[$token]) ? $maxSpaces[$token] + 1 : 1;

        if (count($words) > $keep) {
            $value = implode(' ', array_slice($words, 0, $keep - 1)) . ($keep > 1 ? ' ' : '') . implode('', array_slice($words, $keep - 1));
        }

        return mb_substr($value, 0, 100);
    }

    /**
     * Add a plain explanation to the Kavenegar errors site owners hit most.
     *
     * @param string $message
     * @return string
     */
    private function withHint($message)
    {
        $hints = [
            '403' => __('The API key is not valid. Copy it again from your Kavenegar panel.', 'wp-sms'),
            '411' => __('The recipient number is not valid.', 'wp-sms'),
            '412' => __('This sender line cannot send to this number. Lines starting with 0018 only send outside Iran; use your domestic line from the Kavenegar panel.', 'wp-sms'),
            '418' => __('Your Kavenegar credit is too low.', 'wp-sms'),
            '424' => __('Template not found. Check the template name after "|" and that Kavenegar has approved it.', 'wp-sms'),
            '431' => __('A template value has a character Kavenegar does not accept.', 'wp-sms'),
            '432' => __('The template in your Kavenegar panel has no %token.', 'wp-sms'),
            '501' => __('This Kavenegar account can only send to its owner\'s phone number. Ask Kavenegar to activate the account for other numbers.', 'wp-sms'),
        ];

        if (preg_match('/\b([45]\d\d)\b/', $message, $match) && isset($hints[$match[1]])) {
            return $message . ' - ' . $hints[$match[1]];
        }

        return $message;
    }
}
