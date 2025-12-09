<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class ghasedak extends Gateway
{
    /**
     * API Base URL.
     *
     * @var string
     */
    private $wsdl_link = "https://gateway.ghasedak.me/";

    /**
     * Pricing page URL.
     *
     * @var string
     */
    public $tariff = "https://ghasedak.me/sms-pricing";

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
     * API Type
     *
     * @var string
     */
    public $api_type;

    /**
     * Template ID.
     *
     * @var string
     */
    public $template_id = null;

    /**
     * Gateway version.
     */
    public $version = '1.0';

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->validateNumber = "09xxxxxxxx";
        $this->gatewayFields  = [
            'api_type' => [
                'id'      => 'gateway_api_type',
                'name'    => __('API Type', 'wp-sms'),
                'type'    => 'select',
                'options' => [
                    'ghasedak.me'     => 'سامانه جدید',
                    'ghasedaksms.com' => 'سامانه قدیم',
                ],
                'desc'    => '',
            ],
            'from'     => [
                'id'           => 'gateway_sender_id',
                'name'         => __('Sender Number', 'wp-sms'),
                'place_holder' => __('e.g., 50002178584000', 'wp-sms'),
                'desc'         => __('Number or sender ID shown on recipient’s device.', 'wp-sms'),
            ],
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => __('API Key', 'wp-sms'),
                'desc' => __('Enter your gateway API key.', 'wp-sms'),
            ],
        ];
        $this->help           = "
<div dir='rtl'><h3>ارسال پیامک با قالب</h3>
<ol>
  <li><strong>قالب را در پنل پیامک ثبت و تأیید کنید</strong><br>
    متن قالب و متغیرها باید دقیقاً همان چیزی باشند که در افزونه می‌نویسید.<br>
    <code style='direction: rtl'>سلام %billing_first_name%، سفارش %order_id% با موفقیت ثبت شد.</code>
  </li>
  <li><strong>در افزونه همان متن را بنویسید و نام قالب را با «|» بعد از متن پیامک اضافه کنید</strong><br>
    <code style='direction: rtl'>سلام %billing_first_name%، سفارش %order_id% با موفقیت ثبت شد.|Ghasedak</code>
  </li>
</ol>

<p><strong>نکات مهم</strong></p>
<ul>
  <li>نام متغیرها باید دقیقاً یکسان باشند؛ مانند <code>%billing_first_name%</code> و <code>%order_id%</code>.</li>
  <li>اگر <code style='direction: rtl'>|نام قالب</code> نگذارید، پیام به‌صورت <em>ارسال معمولی</em> فرستاده می‌شود.</li>
  <li>در سامانه قدیم هم از دستورالعمل ذکر شده استفاده کنید.</li>

  <br>
  <li><strong>سامانه جدید (ghasedak.me):</strong> وارد کردن IP برای ارسال پیامک <strong>الزامی</strong> است.<br>هنگام ایجاد API Key آی پی سرور و یا هاست خود را وارد کنید.</li>

  <li><strong>سامانه قدیم (ghasedaksms.com):</strong> وارد کردن IP <strong>الزامی نیست</strong>.<br>برای وارد کردن آی پی به پنل کاربری / تنظیمات / تنظیمات اکانت / امنیتی مراجعه کنید.</li>
</ul>
</div>";
    }

    /**
     * Send SMS message.
     */
    public function SendSMS()
    {
        if (empty($this->has_key)) {
            return new WP_Error('missing-api-key', __('API Key is required.', 'wp-sms'));
        }

        $this->setTemplateIdAndMessageBody();

        $this->from = apply_filters('wp_sms_from', $this->from);
        $this->to   = apply_filters('wp_sms_to', $this->to);
        $this->msg  = apply_filters('wp_sms_msg', $this->msg);

        $isBulk = count($this->to) > 1;

        $isTemplate = !empty($this->template_id) && !empty($this->messageVariables);

        try {
            $endpointType = 'sms';
            if ($isTemplate) {
                $endpointType = 'template';
            } elseif ($isBulk) {
                $endpointType = 'bulk';
            }
            $url = $this->resolveApiEndpoint($endpointType);

            if ($isTemplate) {
                $body = $this->buildTemplatePayload();
            } else {
                $body = $this->buildSmsPayload($isBulk);
            }

            $params = [
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                    'ApiKey'       => $this->has_key,
                ],
                'body'    => wp_json_encode($body),
            ];

            $response = $this->request('POST', $url, [], $params);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $errorMessage = $this->getErrorMessageOldApi($response);
            if ($errorMessage !== null) {
                throw new Exception($errorMessage);
            }

            if (isset($response->isSuccess) && !$response->isSuccess) {
                $msg = !empty($response->message) ? $response->message : __('Gateway request failed.', 'wp-sms');

                throw new Exception($msg);
            }

            $this->log($this->from, $this->msg, $this->to, $response);

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
            $params = [
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                    'ApiKey'       => $this->has_key,
                ],
            ];

            $response = $this->request('GET', $this->resolveApiEndpoint('balance'), [], $params);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            return $this->parseCredit($response->data);
        } catch (Exception $e) {
            return new WP_Error('account-credit-error', $e->getMessage());
        }
    }

    /**
     * Resolve API endpoint URL based on type and selected API.
     *
     * @param string $type 'sms' or 'balance' (or other future types).
     *
     * @return string
     */
    private function resolveApiEndpoint($type = 'sms')
    {
        $apiType = ($this->api_type === 'ghasedaksms.com') ? 'ghasedaksms.com' : 'ghasedak.me';

        $bases = [
            'ghasedak.me'     => 'https://gateway.ghasedak.me/',
            'ghasedaksms.com' => 'https://gateway.ghasedaksms.com/',
        ];

        $paths = [
            'balance'  => [
                'ghasedak.me'     => 'rest/api/v1/WebService/GetAccountInformation',
                'ghasedaksms.com' => 'api/v1/Account/AccountInfo',
            ],
            'sms'      => [
                'ghasedak.me'     => 'rest/api/v1/WebService/SendSingleSMS',
                'ghasedaksms.com' => 'api/v1/Send/Simple',
            ],
            'bulk'     => [
                'ghasedak.me'     => 'rest/api/v1/WebService/SendBulkSMS',
                'ghasedaksms.com' => 'api/v1/Send/Bulk',
            ],
            'template' => [
                'ghasedak.me'     => 'rest/api/v1/WebService/SendOtpSMS',
                'ghasedaksms.com' => 'api/v1/Send/NewOTP',
            ],
        ];

        if (!isset($paths[$type])) {
            return $bases[$apiType];
        }

        return $bases[$apiType] . $paths[$type][$apiType];
    }

    /**
     * Parse credit value from both API versions.
     *
     * @param object $data Response data object.
     * @return float|null Credit amount or null if not found.
     */
    private function parseCredit($data)
    {
        if (isset($data->credit)) {
            return $data->credit;
        }

        if (isset($data->balance)) {
            return $data->balance;
        }

        return null;
    }

    /**
     * Build request body for single SMS (new API).
     *
     * @return array
     */
    private function buildNewApiSinglePayload()
    {
        return [
            'lineNumber' => $this->from,
            'message'    => $this->msg,
            'receptor'   => reset($this->to) ?: $this->to,
        ];
    }

    /**
     * Build request body for bulk SMS (new API).
     *
     * @return array
     */
    private function buildNewApiBulkPayload()
    {
        return [
            'lineNumber' => $this->from,
            'message'    => $this->msg,
            'receptors'  => array_values($this->to),
        ];
    }

    /**
     * Build request body for single SMS (legacy API).
     *
     * @return array
     */
    private function buildOldApiSinglePayload()
    {
        return [
            'sender'   => $this->from,
            'message'  => $this->msg,
            'receptor' => (string)reset($this->to) ?: $this->to,
        ];
    }

    /**
     * Build request body for bulk SMS (legacy API).
     *
     * @return array
     */
    private function buildOldApiBulkPayload()
    {
        return [
            'sender'   => $this->from,
            'message'  => $this->msg,
            'receptor' => implode(',', array_values($this->to)),
        ];
    }

    /**
     * Build request payload for SMS sending based on API type and mode.
     *
     * @param bool $isBulk
     * @return array
     */
    private function buildSmsPayload($isBulk = false)
    {
        $isNewApi = ($this->api_type !== 'ghasedaksms.com');

        if ($isNewApi) {
            return $isBulk ? $this->buildNewApiBulkPayload() : $this->buildNewApiSinglePayload();
        }

        return $isBulk ? $this->buildOldApiBulkPayload() : $this->buildOldApiSinglePayload();
    }

    /**
     * Build payload for Ghasedak new API template (OTP) sending.
     *
     * Formats recipients and template input parameters according to the new API structure.
     *
     * @return array Formatted request body for template SMS.
     */
    private function buildNewApiTemplatePayload()
    {
        $receptors = [];
        foreach ($this->to as $index => $mobile) {
            $receptors[] = [
                'mobile'            => (string)$mobile,
                'clientReferenceId' => (string)($index + 1),
            ];
        }

        $inputs = [];
        foreach ($this->messageVariables as $key => $value) {
            $inputs[] = [
                'param' => (string)$key,
                'value' => (string)$value,
            ];
        }

        return [
            'receptors'    => $receptors,
            'templateName' => (string)$this->template_id,
            'inputs'       => $inputs,
        ];
    }

    /**
     * Build payload for Ghasedak legacy API template (OTP) sending.
     *
     * Converts recipients and variables into the legacy "template + allparam" structure.
     *
     * @return array Formatted request payload for legacy template SMS.
     */
    private function buildOldApiTemplatePayload()
    {
        $receptors = implode(',', array_values($this->to));

        $allparam = [];
        foreach ($this->messageVariables as $key => $value) {
            $allparam[] = [
                'param' => (string)$key,
                'value' => (string)$value,
            ];
        }

        return [
            'receptor' => $receptors,
            'type'     => '1',
            'template' => (string)$this->template_id,
            'allparam' => $allparam,
        ];
    }

    /**
     * Generates template-based SMS payload.
     *
     * Includes message parameters and OTP/template mappings.
     *
     * @return array Structured template data array.
     */
    private function buildTemplatePayload()
    {
        $isNewApi = ($this->api_type !== 'ghasedaksms.com');

        if ($isNewApi) {
            return $this->buildNewApiTemplatePayload();
        }

        return $this->buildOldApiTemplatePayload();
    }

    /**
     * Sets the template ID based on the current message.
     *
     * @return void
     */
    private function setTemplateIdAndMessageBody()
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
     * Converts legacy Ghasedak API error codes into human-readable messages.
     *
     * @param object $response
     *
     * @return string|null
     */
    private function getErrorMessageOldApi($response)
    {
        if (empty($response->data) || !is_array($response->data)) {
            return null;
        }

        $failed  = [];
        $success = [];

        foreach ($response->data as $key => $errorCode) {
            $mobile = isset($this->to[$key]) ? $this->to[$key] : $key;

            if ($errorCode > 1000) {
                $success[] = $mobile;
                continue;
            }

            switch ($errorCode) {
                case 1:
                    $message = 'نام کاربری یا رمز عبور معتبر نمی باشد.';
                    break;
                case 2:
                    $message = 'آرایه ها خالی می باشد.';
                    break;
                case 3:
                    $message = 'طول آرایه بیشتر از 100 می باشد.';
                    break;
                case 4:
                    $message = 'طول آرایه ی فرستنده و گیرنده و متن پیام با یکدیگر تطابق ندارد.';
                    break;
                case 5:
                    $message = 'امکان گرفتن پیام جدید وجود ندارد.';
                    break;
                case 6:
                    $message = "- حساب کاربری غیر فعال می باشد.\n"
                        . "- نام کاربری و یا رمز عبور خود را به درستی وارد نمی کنید.\n"
                        . "- در صورتی که به تازگی وب سرویس را فعال کرده اید رمز عبور وب سرویس خود را مجدد ست کنید.";
                    break;
                case 7:
                    $message = 'امکان دسترسی به خط مورد نظر وجود ندارد';
                    break;
                case 8:
                    $message = 'شماره گیرنده نامعتبر است.';
                    break;
                case 9:
                    $message = 'حساب اعتبار ریالی مورد نیاز را دارا نمی باشد.';
                    break;
                case 10:
                    $message = 'خطایی در سیستم رخ داده است، دوباره سعی کنید.';
                    break;
                case 11:
                    $message = 'IP نامعتبر می باشد.';
                    break;
                case 20:
                    $message = 'شماره مخاطب فیلتر شده می باشد.';
                    break;
                case 21:
                    $message = 'ارتباط با سرویس دهنده قطع می باشد.';
                    break;
                default:
                    $message = 'خطای ناشناخته‌ای رخ داده است.';
                    break;
            }

            $failed[$mobile] = $message;
        }

        if (empty($failed)) {
            return null;
        }

        $lines = ['گزارش وضعیت ارسال پیامک:'];

        if (!empty($success)) {
            $lines[] = "موفق:";
            foreach ($success as $mobile) {
                $lines[] = "{$mobile}";
            }
        }

        $lines[] = "ناموفق:";
        foreach ($failed as $mobile => $msg) {
            $lines[] = "{$mobile}: {$msg}";
        }

        return implode("\n", $lines);
    }
}