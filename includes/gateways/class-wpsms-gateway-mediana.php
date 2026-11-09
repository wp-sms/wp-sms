<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Mediana, on its REST API at api.mediana.ir.
 *
 * Mediana documents one API today: the Postman collection "Mediana API
 * Documentation" at https://apidoc.mediana.ir/. The old SOAP service at
 * panel.mediana.ir, which this gateway used with the panel's username and
 * password, is no longer documented and no longer loads, so the gateway sends
 * through the REST API with an API key instead.
 *
 * The current spec is at https://developer.mediana.ir/spec/en.
 *
 *   Auth: `X-API-KEY: <key>` on every request.
 *   POST /sms/v1/send/sms        {sendingNumber | type, recipients: [up to 100], messageText}
 *   POST /sms/v1/send/pattern    {sendingNumber | type, recipients: [exactly 1], patternCode, parameters: {name: "value"}}
 *   GET  /sms/v1/account/balance -> {"data": {"balance": 9358}}
 *
 * New Mediana accounts have no dedicated line, only Mediana's shared lines.
 * With no sender number set, the gateway sends `"type": "Informational"`
 * instead of `sendingNumber`, and Mediana picks its shared service line.
 *
 * Pattern (template) sending follows the plugin's "message|TEMPLATE_ID"
 * convention. The user types the message with the pattern code after a pipe:
 *
 *   Your code is %code%|815422
 *
 * and registers the pattern in the Mediana panel with the same variable names
 * in double braces:
 *
 *   Your code is {{code}}
 *
 * Each WSMS variable fills the Mediana placeholder of the same name, without
 * the percent signs: %billing_first_name% fills {{billing_first_name}}. A
 * pattern goes to one recipient per request, so the gateway sends one request
 * per number. A message with no "|code", or with no variables in it, is sent
 * as a plain SMS.
 *
 * A send answers `{"meta": {"code": "OK"}, "data": {"succeed": true, "requestId": 1, ...}}`.
 * A refused one answers `{"meta": {"code": "BadRequest", "errorMessage": "...",
 * "errors": [{"errorCode": 1042}]}}`, and a 401 has no body at all.
 *
 * The host answers only from servers inside Iran.
 */
class mediana extends \WP_SMS\Gateway
{
    public $tariff = "https://mediana.ir/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $has_key = true;

    private $wsdl_link = "https://api.mediana.ir/sms/v1";

    /** Recipients Mediana takes in one send. */
    const MAX_RECIPIENTS = 100;

    /** The message type that sends from Mediana's shared service line. */
    const SHARED_LINE_TYPE = 'Informational';

    /**
     * Pattern code taken from the "message|code" text, if any.
     *
     * @var string|null
     */
    public $template_id = null;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxxx";

        $this->gatewayFields = [
            'from'    => [
                'id'           => 'gateway_sender_id',
                'name'         => __('Sender Number', 'wp-sms'),
                'place_holder' => __('e.g., 3000505', 'wp-sms'),
                'desc'         => __('Your dedicated Mediana line. Leave empty to send from Mediana\'s shared line.', 'wp-sms'),
            ],
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => __('API Key', 'wp-sms'),
                'desc' => __('Find it in your Mediana panel. The API uses this key, not the username and password you sign in with.', 'wp-sms'),
            ],
        ];

        $this->documentUrl = 'https://wsms.io/fa/docs/mediana-gateway-setup/';

        $this->help = '
<div dir="rtl">
  <p><strong>کلید API:</strong> در پنل مدیانا (app.mediana.ir) از بخش توسعه دهندگان ← کلیدهای دسترسی یک کلید بسازید و کپی کنید. نام کاربری و رمز عبور پنل دیگر استفاده نمی شود.</p>
  <p><strong>شماره ارسال کننده (اختیاری):</strong> خط اختصاصی خود را وارد کنید، یا خالی بگذارید تا پیام از خط خدماتی مشترک مدیانا (مثلا 983000505) ارسال شود.</p>
  <p><strong>ارسال با الگو (پترن):</strong></p>
  <ol>
    <li>در پنل مدیانا از توسعه دهندگان ← پترن ← ساخت پترن دستی، متن را بنویسید و متغیرها را به شکل <code>{{name}}</code> بگذارید. مدیانا پترن را فقط وقتی تایید می کند که نام فروشگاه یا برند داخل متن نوشته شده باشد (نه به صورت متغیر) و آدرس سایت در متن یا توضیح پترن باشد. هر متغیر را فقط یک بار به کار ببرید.<br>
      مثال: <code>فروشگاه نمونه: کد ورود شما {{code}} example.com</code></li>
    <li>بعد از تایید مدیانا، در افزونه همان پیام را با متغیرهای افزونه بنویسید و بعد از <code>|</code> کد پترن (عدد) را بگذارید.<br>
      مثال: <code>فروشگاه نمونه: کد ورود شما %code% example.com|815422</code></li>
  </ol>
  <p>هر متغیر افزونه جای متغیر هم نام در پترن می نشیند، بدون علامت درصد: <code>%billing_first_name%</code> جای <code>{{billing_first_name}}</code>. پیام الگو برای هر گیرنده جدا ارسال می شود.</p>
  <p>الگو فقط برای پیام های خودکار دارای متغیر کار می کند، مانند کد ورود و پیامک های ووکامرس. ارسال دستی و گروهی را بدون <code>|</code> بنویسید تا به صورت پیام عادی ارسال شود.</p>
</div>';
    }

    /**
     * Takes the pattern code off the end of "message|code".
     *
     * @return void
     */
    public function setTemplateIdAndMessageBody()
    {
        $this->template_id = null;
        $templateData      = $this->getTemplateIdAndMessageBody();

        if (!empty($templateData['template_id'])) {
            $this->template_id = $templateData['template_id'];
        }

        if (!empty($templateData['message'])) {
            $this->msg = $templateData['message'];
        }
    }

    public function SendSMS()
    {
        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         * @since 3.4
         *
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);
        $this->setTemplateIdAndMessageBody();

        if (!$this->hasKey()) {
            $error = $this->missingKey();
            $this->log($this->from, $this->msg, $this->to, $error->get_error_message(), 'error');

            return $error;
        }

        list($recipients, $invalid) = $this->splitRecipients($this->to, function ($number) {
            return $this->localNumber($number);
        });

        if (!$recipients) {
            $error = new \WP_Error('send-sms', esc_html__('Mediana: no valid recipient number.', 'wp-sms'));
            $this->log($this->from, $this->msg, $this->to, $error->get_error_message(), 'error');

            return $error;
        }

        if (!empty($this->template_id) && !empty($this->messageVariables) && is_array($this->messageVariables)) {
            $outcome = $this->sendPattern($recipients);
        } else {
            $outcome = $this->sendPlain($recipients);
        }

        return $this->finishBatchSend($invalid, $outcome, 'Mediana');
    }

    /**
     * Mediana's errors about one number (not a valid number, on the
     * blacklist) let the send go on to the next request. Any other error, such
     * as no credit, no plan, a refused key or a rejected pattern, would repeat
     * for every request, so the send stops.
     */
    protected function stopsBatchSend($error)
    {
        return !in_array($error->get_error_code(), ['mediana-1041', 'mediana-1047'], true);
    }

    /**
     * Mediana may accept a request while marking individual SMS items as
     * blacklisted. Keep those numbers out of the successful outbox entry.
     */
    protected function batchRecipients(array $batch, $data)
    {
        if (empty($data['smsItems']) || !is_array($data['smsItems'])) {
            return parent::batchRecipients($batch, $data);
        }

        $failedIndexes = [];

        foreach ($data['smsItems'] as $itemIndex => $item) {
            if (!is_array($item) || empty($item['isInBlackList'])) {
                continue;
            }

            $recipient = isset($item['recipient']) ? $this->localNumber($item['recipient']) : '';
            $batchIndex = $recipient === '' ? false : array_search($recipient, $batch, true);

            if ($batchIndex === false && isset($batch[$itemIndex])) {
                $batchIndex = $itemIndex;
            }

            if ($batchIndex !== false) {
                $failedIndexes[$batchIndex] = true;
            }
        }

        if (!$failedIndexes) {
            return parent::batchRecipients($batch, $data);
        }

        $sent   = [];
        $failed = [];

        foreach ($batch as $index => $recipient) {
            if (isset($failedIndexes[$index])) {
                $failed[] = $recipient;
            } else {
                $sent[] = $recipient;
            }
        }

        return [
            'sent'   => $sent,
            'failed' => $failed,
            'error'  => new \WP_Error('mediana-1047', esc_html__('Mediana: the number is on the blacklist.', 'wp-sms')),
        ];
    }

    /**
     * Sends the text to up to 100 numbers per request.
     *
     * @return array{results: array[], sent: string[], failed: string[], error: \WP_Error|null}
     */
    private function sendPlain($recipients)
    {
        return $this->sendInBatches(array_chunk($recipients, self::MAX_RECIPIENTS), function ($chunk) {
            return $this->send('/send/sms', $this->lineFields() + [
                'recipients'  => $chunk,
                'messageText' => (string)$this->msg,
            ]);
        });
    }

    /**
     * Sends the pattern once per number: Mediana takes one recipient per
     * pattern request. Variables go by name, so %code% fills {{code}}.
     *
     * @return array{results: array[], sent: string[], failed: string[], error: \WP_Error|null}
     */
    private function sendPattern($recipients)
    {
        $parameters = [];

        foreach ($this->messageVariables as $name => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $parameters[trim((string)$name, '%')] = is_scalar($value) || $value === null ? (string)$value : '';
        }

        return $this->sendInBatches(array_chunk($recipients, 1), function ($one) use ($parameters) {
            return $this->send('/send/pattern', $this->lineFields() + [
                'recipients'  => $one,
                'patternCode' => (string)$this->template_id,
                'parameters'  => (object)$parameters,
            ]);
        });
    }

    /**
     * The sender number when one is set, or the shared line type when not.
     */
    private function lineFields()
    {
        $from = trim((string)$this->from);

        if ($from === '') {
            return ['type' => self::SHARED_LINE_TYPE];
        }

        return ['sendingNumber' => $from];
    }

    /**
     * Posts one send and checks that Mediana confirmed it.
     *
     * @return array|\WP_Error
     */
    private function send($path, $body)
    {
        $data = $this->callApi('POST', $path, $body);

        if (is_wp_error($data)) {
            if ($data->get_error_code() === 'mediana-1101' && isset($body['sendingNumber'])) {
                return new \WP_Error('mediana-1101', sprintf(
                    /* translators: %s: the sender number in the gateway settings */
                    esc_html__('Mediana does not know the sender number %s in your account (error 1101). Fix the number in the gateway settings, or leave it empty to send from Mediana\'s shared line.', 'wp-sms'),
                    esc_html($body['sendingNumber'])
                ));
            }

            return $data;
        }

        if (empty($data['succeed']) || empty($data['requestId'])) {
            $said = isset($data['message']) && is_scalar($data['message']) ? trim((string)$data['message']) : '';

            return new \WP_Error('send-sms', $said !== '' ? $said : esc_html__('Mediana did not confirm the send.', 'wp-sms'));
        }

        return $data;
    }

    public function GetCredit()
    {
        if (!$this->hasKey()) {
            return $this->missingKey();
        }

        $data = $this->callApi('GET', '/account/balance');

        if (is_wp_error($data)) {
            return new \WP_Error('account-credit', $data->get_error_message());
        }

        if (!isset($data['balance']) || !is_numeric($data['balance'])) {
            return new \WP_Error('account-credit', esc_html__('Mediana did not return a balance.', 'wp-sms'));
        }

        return $data['balance'];
    }

    /**
     * Calls the API and returns the answer's `data`, or a WP_Error that says
     * what Mediana said.
     *
     * @return array|\WP_Error
     */
    private function callApi($method, $path, $body = null)
    {
        $args = [
            'method'  => $method,
            'timeout' => 15,
            'headers' => [
                'X-API-KEY' => trim((string)$this->has_key),
                'Accept'    => 'application/json',
            ],
        ];

        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body']                    = wp_json_encode($body);
        }

        $response = wp_remote_request($this->wsdl_link . $path, $args);

        if (is_wp_error($response)) {
            /* translators: %s: error message */
            return new \WP_Error('gateway-unreachable', sprintf(esc_html__('Mediana could not be reached: %s', 'wp-sms'), $response->get_error_message()));
        }

        $http   = (int)wp_remote_retrieve_response_code($response);
        $answer = json_decode(wp_remote_retrieve_body($response), true);
        $meta   = is_array($answer) && isset($answer['meta']) && is_array($answer['meta']) ? $answer['meta'] : [];

        if ($http >= 200 && $http < 300 && is_array($answer) && isset($answer['data']) && is_array($answer['data'])
            && (!isset($answer['meta']) || (isset($meta['code']) && $meta['code'] === 'OK'))) {
            return $answer['data'];
        }

        $said = isset($meta['errorMessage']) && is_scalar($meta['errorMessage']) ? trim((string)$meta['errorMessage']) : '';

        if (!empty($meta['errors']) && is_array($meta['errors'])) {
            foreach ($meta['errors'] as $error) {
                $code = is_array($error) && isset($error['errorCode']) && is_scalar($error['errorCode']) ? (string)$error['errorCode'] : '';
                $text = $this->errorText($code);

                if ($text !== '') {
                    /* translators: 1: Mediana error code, 2: what it means */
                    return new \WP_Error('mediana-' . $code, sprintf(esc_html__('Mediana refused the request (error %1$s): %2$s.', 'wp-sms'), $code, $text));
                }
            }
        }

        if ($said !== '') {
            /* translators: %s: Mediana's own message */
            return new \WP_Error('mediana-refused', sprintf(esc_html__('Mediana refused the request: %s', 'wp-sms'), $said));
        }

        if ($http === 401 || $http === 403) {
            return new \WP_Error('mediana-auth', esc_html__('Mediana refused the API key. Copy it again from your Mediana panel.', 'wp-sms'));
        }

        if ($http === 502) {
            return new \WP_Error('gateway-unreachable', esc_html__('Mediana answered HTTP 502. Mediana only answers servers inside Iran, so check where your site is hosted.', 'wp-sms'));
        }

        /* translators: %d: HTTP status */
        return new \WP_Error('mediana-http', sprintf(esc_html__('Mediana answered HTTP %d.', 'wp-sms'), $http));
    }

    /**
     * Mediana takes numbers in the local form only: 09xxxxxxxxx.
     */
    private function localNumber($number)
    {
        $number = preg_replace('/[^0-9]/', '', (string)$number);

        if (strpos($number, '0098') === 0) {
            $number = substr($number, 4);
        } elseif (strpos($number, '98') === 0 && strlen($number) === 12) {
            $number = substr($number, 2);
        }

        if (strpos($number, '9') === 0 && strlen($number) === 10) {
            $number = '0' . $number;
        }

        return $number;
    }

    private function hasKey()
    {
        return is_string($this->has_key) && trim($this->has_key) !== '';
    }

    private function missingKey()
    {
        return new \WP_Error('account-credit', esc_html__('Enter your Mediana API key. The username and password are no longer used.', 'wp-sms'));
    }

    /**
     * The error codes in the description of Mediana's API spec.
     */
    private function errorText($code)
    {
        $codes = [
            '1021' => __('an unknown error occurred', 'wp-sms'),
            '1032' => __('the account has no active plan', 'wp-sms'),
            '1033' => __('the account\'s plan does not include the API', 'wp-sms'),
            '1034' => __('the account\'s plan does not include pattern sending', 'wp-sms'),
            '1035' => __('the account\'s plan does not include a dedicated line', 'wp-sms'),
            '1041' => __('the recipient\'s number is not valid', 'wp-sms'),
            '1042' => __('the wallet balance is not enough', 'wp-sms'),
            '1043' => __('there are more recipients than allowed', 'wp-sms'),
            '1044' => __('the SMS ID is not valid', 'wp-sms'),
            '1045' => __('the request code is not valid', 'wp-sms'),
            '1046' => __('the request\'s parameters are not valid', 'wp-sms'),
            '1047' => __('the number is on the blacklist', 'wp-sms'),
            '1048' => __('WebEngage is not enabled on the account', 'wp-sms'),
            '1051' => __('the campaign has expired', 'wp-sms'),
            '1061' => __('no active line was found', 'wp-sms'),
            '1062' => __('the line cannot be used at this time of day', 'wp-sms'),
            '1071' => __('the pattern contains a URL', 'wp-sms'),
            '1072' => __('the pattern was rejected by Mediana; check its status in your Mediana panel', 'wp-sms'),
            '1073' => __('the pattern belongs to another sending number; send from the number it was approved for', 'wp-sms'),
            '1074' => __('the message text is empty', 'wp-sms'),
            '1075' => __('the message request was not found', 'wp-sms'),
            '1076' => __('the pattern is empty', 'wp-sms'),
            '1081' => __('the account\'s postal code has not been verified', 'wp-sms'),
            '1082' => __('the account\'s national code has not been verified', 'wp-sms'),
            '1083' => __('the account\'s mobile number has not been verified', 'wp-sms'),
            '1084' => __('the account\'s profile is not complete', 'wp-sms'),
            '1093' => __('no recipients were found', 'wp-sms'),
            '1101' => __('the sending number was not found', 'wp-sms'),
            '1102' => __('the sending number has expired', 'wp-sms'),
        ];

        return isset($codes[$code]) ? esc_html($codes[$code]) : '';
    }
}
