<?php

namespace WP_SMS\Gateway;

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
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->gatewayFields = [
            'username'         => [
                'id'   => 'gateway_username',
                'name' => __('API Username', 'wp-sms'),
                'desc' => __('Enter the API username provided by your SMS gateway.', 'wp-sms'),
            ],
            'password'         => [
                'id'   => 'gateway_password',
                'name' => __('API Password', 'wp-sms'),
                'desc' => __('Enter the API password provided by your SMS gateway.', 'wp-sms'),
            ],
            'from'             => [
                'id'   => 'gateway_sender_id',
                'name' => __('Sender Number', 'wp-sms'),
                'desc' => __('Enter the sender number or sender ID registered with your SMS gateway.', 'wp-sms'),
            ],
            'from_support_one' => [
                'id'   => 'gateway_support_1_sender_id',
                'name' => __('Backup sender 1 (optional)', 'wp-sms'),
                'desc' => __('Optional: support sender number used with Smart SMS.', 'wp-sms'),
            ],
            'from_support_two' => [
                'id'   => 'gateway_support_2_sender_id',
                'name' => __('Backup sender 2 (optional)', 'wp-sms'),
                'desc' => __('Optional: secondary support sender used with Smart SMS.', 'wp-sms'),
            ],
        ];

        $this->help = <<<HTML
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
      <code style='direction: rtl'>سلام %billing_first_name%، سفارش %order_id% با موفقیت ثبت شد.</code><br>
      <code>سلام {0}، سفارش {1} با موفقیت ثبت شد.</code>
    </li>
    <li>
      <strong>اضافه کردن کد الگو در پلاگین</strong><br>
      بعد از متن پیامک، کد الگو را با علامت «|» اضافه کنید.<br>
      مثال: <code style='direction: rtl'>سلام %billing_first_name%، سفارش %order_id% با موفقیت ثبت شد.|2343</code>
    </li>
  </ol>
  <p><strong>نکات مهم</strong></p>
  <ul>
    <li>اگر <code style='direction: rtl'>|کد</code> نگذارید، پیام به‌صورت <em>ارسال معمولی</em> فرستاده می‌شود.</li>
  </ul>
</div>
HTML;
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
            return new WP_Error('missing-credentials', __('API Username and API Password are required.', 'wp-sms'));
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

            if (isset($response->RetStatus) && $response->RetStatus !== 1) {
                throw new Exception($this->getErrorMessage($response->RetStatus));
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
                'body'    => $body,
            ];

            $response = $this->request('POST', $this->wsdl_link . 'SendSMS/GetCredit', [], $params, false);

            if (isset($response->RetStatus)) {
                if ($response->RetStatus === 1) {
                    return $response->Value ?? 0;
                }

                throw new Exception($this->getErrorMessage($response->RetStatus));
            }

            throw new Exception(__('Invalid response from SMS service.', 'wp-sms'));

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
        $recipients          = $this->formatReceiverNumbers($this->to);
        $recipientsFormatted = count($recipients) > 1 ? implode(',', $recipients) : $recipients[0];
        $body                = [
            'username' => $this->username,
            'password' => $this->password,
            'from'     => $this->from,
            'to'       => $recipientsFormatted,
            'text'     => $this->msg,
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
            'body'    => $body,
        ];

        return $this->request('POST', $this->wsdl_link . 'SmartSMS/Send', [], $params, false);
    }

    /**
     * Send SMS using template-based API (Service-Line).
     *
     * @return object|WP_Error API response object, or WP_Error on failure.
     * @throws Exception If request fails.
     */
    private function sendTemplateSMS()
    {
        if (empty($this->messageVariables)) {
            return new WP_Error('invalid-template', __('Message does not contain valid template placeholders.', 'wp-sms'));
        }

        $receivers     = $this->formatReceiverNumbers($this->to);
        $responses     = [];
        $messageValues = array_values($this->messageVariables);

        foreach ($receivers as $receiver) {
            $body   = [
                'username' => $this->username,
                'password' => $this->password,
                'text'     => implode(';', $messageValues),
                'to'       => $receiver,
                'bodyId'   => (int)$this->template_id,
            ];
            $params = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body'    => $body,
            ];

            $responses[] = $this->request('POST', $this->wsdl_link . 'SendSMS/BaseServiceNumber', [], $params, false);
        }

        return end($responses);
    }

    /**
     * Format receiver phone numbers.
     *
     * Ensures numbers are in local `09xxxxxxxxx` format.
     *
     * @param array|string $numbers Phone number(s).
     *
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
                $message = esc_html__('Invalid username or password.', 'wp-sms');
                break;
            case 2:
                $message = esc_html__('Insufficient credit.', 'wp-sms');
                break;
            case 3:
                $message = esc_html__('Daily sending limit reached.', 'wp-sms');
                break;
            case 4:
                $message = esc_html__('Sending volume limit reached.', 'wp-sms');
                break;
            case 5:
                $message = esc_html__('Invalid sender number.', 'wp-sms');
                break;
            case 6:
                $message = esc_html__('System is under maintenance.', 'wp-sms');
                break;
            case 7:
                $message = esc_html__('Message contains a filtered word.', 'wp-sms');
                break;
            case 9:
                $message = esc_html__('Sending from public lines via web service is not allowed.', 'wp-sms');
                break;
            case 10:
                $message = esc_html__('User is not active.', 'wp-sms');
                break;
            case 11:
                $message = esc_html__('Message not sent.', 'wp-sms');
                break;
            case 12:
                $message = esc_html__('User documents are incomplete.', 'wp-sms');
                break;
            case 14:
                $message = esc_html__('Message contains a link.', 'wp-sms');
                break;
            case 15:
                $message = esc_html__('Cannot send to more than one recipient without including "لغو11".', 'wp-sms');
                break;
            case 16:
                $message = esc_html__('No recipient number found.', 'wp-sms');
                break;
            case 17:
                $message = esc_html__('Message text is empty.', 'wp-sms');
                break;
            case 18:
                $message = esc_html__('Invalid recipient number.', 'wp-sms');
                break;
            case 35:
                $message = esc_html__('Number is in the telecom blacklist.', 'wp-sms');
                break;
            default:
                $message = esc_html__('Unknown error occurred.', 'wp-sms');
                break;
        }

        return $message;
    }
}