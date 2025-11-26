<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class melipayamak extends Gateway
{
    /**
     * API Base URL.
     *
     * @var string
     */
    private $wsdl_link = "https://rest.payamak-panel.com/api/";

    /**
     * Pricing page URL.
     *
     * @var string
     */
    public $tariff = "https://www.melipayamak.com/price/";

    /**
     * Determines how the account balance unit is represented.
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
     * Template ID for Service-Line API.
     *
     * @var string
     */
    public $template_id = null;

    /**
     * Backup sender number 1.
     *
     * @var string
     */
    public $from_support_one = '';

    /**
     * Backup sender number 2.
     *
     * @var string
     */
    public $from_support_two = '';

    /**
     * Gateway document url
     *
     * @var string
     */
    public $documentUrl = "https://www.melipayamak.com/lab/wordpress-sending-sms-plugin-wp-smsn/";

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

        $this->validateNumber = "09xxxxxxxx";

        $this->gatewayFields = [
            'username' => [
                'id' => 'gateway_username',
                'name' => esc_html__('API Username', 'wp-sms'),
                'desc' => esc_html__('Enter the API username provided by your SMS gateway.', 'wp-sms'),
            ],
            'password' => [
                'id' => 'gateway_password',
                'name' => esc_html__('API Password', 'wp-sms'),
                'desc' => esc_html__('Enter the API password provided by your SMS gateway.', 'wp-sms'),
            ],
            'from' => [
                'id' => 'gateway_sender_id',
                'name' => esc_html__('Sender Number', 'wp-sms'),
                'desc' => esc_html__('Enter the sender number or sender ID registered with your SMS gateway.', 'wp-sms'),
            ],
            'from_support_one' => [
                'id' => 'gateway_support_1_sender_id',
                'name' => esc_html__('Backup sender 1 (optional)', 'wp-sms'),
                'desc' => esc_html__('Optional: support sender number used with Smart SMS.', 'wp-sms'),
            ],
            'from_support_two' => [
                'id' => 'gateway_support_2_sender_id',
                'name' => esc_html__('Backup sender 2 (optional)', 'wp-sms'),
                'desc' => esc_html__('Optional: secondary support sender used with Smart SMS.', 'wp-sms'),
            ],
        ];

        $this->help = '
<div dir="rtl">
  <h3>ارسال پیامک با پترن (الگو)</h3>
  <ol>
    <li>
      <strong>ثبت الگو در پنل پیامک</strong><br>
      متن پیامک باید شامل نام متغیرها باشد.  
      <ul>
        <li>در <strong>پلاگین</strong>: نام متغیرها باید بین <code>%</code> قرار بگیرند؛ مانند <code>%billing_first_name%</code> و <code>%order_id%</code>.</li>
        <li>در <strong>سامانه پیامکی</strong>: متغیرها به ترتیب شماره‌گذاری می‌شوند؛ مانند <code>{0}</code> و <code>{1}</code>.</li>
      </ul>
      مثال متن پیامک:<br>
      <code style="direction: rtl">سلام %billing_first_name%، سفارش %order_id% با موفقیت ثبت شد.</code><br>
      <code>سلام {0}، سفارش {1} با موفقیت ثبت شد.</code>
    </li>
    <li>
      <strong>اضافه کردن کد الگو در پلاگین</strong><br>
      بعد از متن پیامک، کد الگو را با علامت «|» اضافه کنید.<br>
      مثال: <code style="direction: rtl">سلام %billing_first_name%، سفارش %order_id% با موفقیت ثبت شد.|2343</code>
    </li>
  </ol>
  <p><strong>نکات مهم</strong></p>
  <ul>
    <li>اگر <code style="direction: rtl">|کد</code> نگذارید، پیام به‌صورت <em>ارسال معمولی</em> فرستاده می‌شود.</li>
  </ul>
</div>';
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
        if (empty($this->username) || empty($this->password)) {
            return new WP_Error('missing-credentials', esc_html__('API Username and API Password are required.', 'wp-sms'));
        }

        $this->setTemplateIdAndMessageBody();

        // Filters for customization.
        $this->from = apply_filters('wp_sms_from', $this->from);
        $this->to = apply_filters('wp_sms_to', $this->to);
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

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

            if (is_array($response) && isset($response['Results']) && is_array($response['Results'])) {
                return $this->handleTemplateSendResponseOrThrow($response);
            }

            $parseSendResultByValue = $this->parseSendResultByValue($response);

            if (!$parseSendResultByValue['ok']) {
                throw new Exception($this->getErrorMessage($parseSendResultByValue['code']));
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
            $body = [
                'username' => $this->username,
                'password' => $this->password,
            ];

            $params = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => $body,
            ];

            $response = $this->request('POST', $this->wsdl_link . 'SendSMS/GetCredit', [], $params, false);

            if (isset($response->RetStatus)) {
                if ($response->RetStatus == 1) {
                    return isset($response->Value) ? (float)$response->Value : 0;
                }

                throw new Exception($this->getErrorMessage($response->RetStatus));
            }

            throw new Exception(esc_html__('Invalid response from SMS service.', 'wp-sms'));
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
        $recipients = $this->to;
        $recipientsFormatted = count($recipients) > 1 ? implode(',', $recipients) : $recipients[0];
        $body = [
            'username' => $this->username,
            'password' => $this->password,
            'from' => $this->from,
            'to' => $recipientsFormatted,
            'text' => $this->msg,
        ];

        if ($this->isflash) {
            $body['isflash'] = $this->isflash;
        }

        if (!empty($this->from_support_one)) {
            $body['fromSupportOne'] = $this->from_support_one;
        }

        if (!empty($this->from_support_two)) {
            $body['fromSupportTwo'] = $this->from_support_two;
        }

        $params = [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $body,
        ];

        return $this->request('POST', $this->wsdl_link . 'SmartSMS/Send', [], $params, false);
    }

    /**
     * Send SMS using template-based API (Service-Line).
     *
     * @return array|WP_Error API response object, or WP_Error on failure.
     */
    private function sendTemplateSMS()
    {
        if (empty($this->messageVariables)) {
            return new WP_Error('invalid-template', esc_html__('Message does not contain valid template placeholders.', 'wp-sms'));
        }

        if (empty($this->template_id)) {
            return new WP_Error('invalid-template-id', esc_html__('Template ID is missing.', 'wp-sms'));
        }

        $messageValues = array_values($this->messageVariables);
        $textPayload = implode(';', $messageValues);

        $results = [];
        $successes = 0;
        $failures = 0;

        foreach ($this->to as $receiver) {
            $body = [
                'username' => $this->username,
                'password' => $this->password,
                'text' => $textPayload,
                'to' => $receiver,
                'bodyId' => (int)$this->template_id,
            ];
            $params = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => $body,
            ];

            $resp = $this->request('POST', $this->wsdl_link . 'SendSMS/BaseServiceNumber', [], $params, false);

            if (is_wp_error($resp)) {
                $failures++;
                $results[] = [
                    'to' => $receiver,
                    'status' => 'error',
                    'errorType' => 'wp_error',
                    'message' => $resp->get_error_message(),
                    'raw' => null,
                ];
                continue;
            }

            $parseSendResultByValue = $this->parseSendResultByValue($resp);

            if (!$parseSendResultByValue['ok']) {
                $failures++;
                $results[] = [
                    'to' => $receiver,
                    'status' => 'error',
                    'code' => $parseSendResultByValue['code'] ?? 'unknown',
                    'message' => $this->getErrorMessage($parseSendResultByValue['code']),
                    'raw' => $resp,
                ];
                continue;
            }

            $successes++;
            $results[] = [
                'to' => $receiver,
                'status' => 'ok',
                'raw' => $resp,
            ];
        }

        $retStatus = $failures == 0 ? 1 : ($successes > 0 ? 206 : 0);

        return [
            'RetStatus' => $retStatus,
            'Summary' => ['success' => $successes, 'failure' => $failures],
            'Results' => $results,
        ];
    }

    /**
     * Get error message from the request error code.
     *
     * @param int|string $errorCode
     * @return string
     */
    private function getErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case 0:
                $message = esc_html__('نام کاربری یا رمز عبور اشتباه می باشد.', 'wp-sms');
                break;
            case 2:
                $message = esc_html__('اعتبار کافی نمی باشد.', 'wp-sms');
                break;
            case 3:
                $message = esc_html__('محدودیت در ارسال روزانه.', 'wp-sms');
                break;
            case 4:
                $message = esc_html__('محدودیت در حجم ارسال.', 'wp-sms');
                break;
            case 5:
                $message = esc_html__('شماره فرستنده معتبر نمی باشد.', 'wp-sms');
                break;
            case 6:
                $message = esc_html__('سامانه در حال بروزرسانی می باشد.', 'wp-sms');
                break;
            case 7:
                $message = esc_html__('متن حاوی کلمه فیلتر شده می باشد.', 'wp-sms');
                break;
            case 9:
                $message = esc_html__('ارسال از خطوط عمومی از طریق وب سرویس امکان پذیر نمی باشد.', 'wp-sms');
                break;
            case 10:
                $message = esc_html__('کاربر مورد نظر فعال نمی باشد.', 'wp-sms');
                break;
            case 11:
                $message = esc_html__('ارسال نشده.', 'wp-sms');
                break;
            case 12:
                $message = esc_html__('مدارک کاربر کامل نمی باشد.', 'wp-sms');
                break;
            case 14:
                $message = esc_html__('متن حاوی لینک می باشد.', 'wp-sms');
                break;
            case 15:
                $message = esc_html__('ارسال به بیش از 1 شماره همراه بدون درج "لغو11" ممکن نیست.', 'wp-sms');
                break;
            case 16:
                $message = esc_html__('شماره گیرنده ای یافت نشد.', 'wp-sms');
                break;
            case 17:
                $message = esc_html__('متن پیامک خالی می باشد.', 'wp-sms');
                break;
            case 18:
                $message = esc_html__('شماره گیرنده نامعتبر است.', 'wp-sms');
                break;
            case 35:
                $message = esc_html__('در REST به معنای وجود شماره در لیست سیاه مخابرات می‌باشد.', 'wp-sms');
                break;
            case -10:
                $message = esc_html__('در میان متغییر های ارسالی ، لینک وجود دارد.', 'wp-sms');
                break;
            case -7:
                $message = esc_html__('خطایی در شماره فرستنده رخ داده است با پشتیبانی تماس بگیرید.', 'wp-sms');
                break;
            case -6:
                $message = esc_html__('خطای داخلی رخ داده است با پشتیبانی تماس بگیرید.', 'wp-sms');
                break;
            case -5:
                $message = esc_html__('متن ارسالی باتوجه به متغیرهای مشخص شده در متن پیشفرض همخوانی ندارد.', 'wp-sms');
                break;
            case -4:
                $message = esc_html__('کد متن ارسالی صحیح نمی‌باشد و یا توسط مدیر سامانه تأیید نشده است.', 'wp-sms');
                break;
            case -3:
                $message = esc_html__('خط ارسالی در سیستم تعریف نشده است، با پشتیبانی سامانه تماس بگیرید.', 'wp-sms');
                break;
            case -2:
                $message = esc_html__('محدودیت تعداد شماره، محدودیت هربار ارسال یک شماره موبایل می‌باشد.', 'wp-sms');
                break;
            case -1:
                $message = esc_html__('دسترسی برای استفاده از این وبسرویس غیرفعال است. با پشتیبانی تماس بگیرید.', 'wp-sms');
                break;
            default:
                $message = esc_html__('خطای ناشناخته‌ای رخ داده است.', 'wp-sms');
                break;
        }

        return $message;
    }

    /**
     * Check Melipayamak send result by Value.
     *
     * @param object|WP_Error $resp
     * @return array
     */
    private function parseSendResultByValue($resp)
    {
        if (is_wp_error($resp) || !is_object($resp) || !isset($resp->Value)) {
            return ['ok' => false, 'recId' => null, 'code' => null, 'raw' => $resp];
        }

        $errorCodes = [0, 2, 3, 4, 5, 6, 7, 9, 10, 11, 12, 14, 15, 16, 17, 18, 35, -10, -7, -6, -5, -4, -3, -2, -1];

        $val = $resp->Value;

        if (in_array($val, $errorCodes)) {
            return ['ok' => false, 'recId' => null, 'code' => $val, 'raw' => $resp];
        }

        return ['ok' => true, 'recId' => $val, 'code' => null, 'raw' => $resp];
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
        if (!isset($response['Results']) || !is_array($response['Results'])) {
            throw new Exception(esc_html__('Invalid template response payload.', 'wp-sms'));
        }

        $successCount = 0;
        $failCount = 0;
        $successNumbers = [];
        $failedNumbers = [];

        foreach ($response['Results'] as $item) {
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

        $ret = $response['RetStatus'] ?? 0;

        if ($ret == 1) {
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

        if ($ret == 206) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new Exception($summary);
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        throw new Exception($summary);
    }
}