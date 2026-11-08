<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * IPPanel, on its new REST API ("IPPanel Edge") at edge.ippanel.com.
 *
 * Many Iranian SMS resellers run their panel on IPPanel, and their users are
 * moving to this API with an API key made in the panel under
 * Developers > Access Keys. The docs are at https://docs.ippanel.com/docs/.
 *
 *   Auth: `Authorization: <api key>` on every request, with no scheme word.
 *   POST /v1/api/send  {sending_type: "webservice", from_number, message, params: {recipients: [...]}}
 *   POST /v1/api/send  {sending_type: "pattern", from_number, code, recipients: [exactly 1], params: {name: "value"}}
 *   GET  /v1/api/payment/credit/mine -> {"data": {"credit": 6084722932.70, ...}, "meta": {...}}
 *
 * Numbers, the sender's and the recipients', go in E.164 form: +989120000000
 * and +983000505.
 *
 * Every answer carries a `meta`: {"status": true, "message_code": "200-1"} on
 * success, or {"status": false, "message": "...", "message_code": "400-1",
 * "errors": {"field": ["..."]}} with HTTP 401 or 422 when refused.
 *
 * Pattern (template) sending follows the plugin's "message|TEMPLATE_ID"
 * convention. The user types the message with the pattern code after a pipe:
 *
 *   Your code is %code%|jhaskdab45s6f4sfw
 *
 * and registers the pattern in the IPPanel panel with the same variable
 * names between percent signs, as IPPanel spells them:
 *
 *   Your code is %code%
 *
 * Each WSMS variable fills the IPPanel variable of the same name. A pattern
 * goes to one recipient per request, so the gateway sends one request per
 * number. A message with no "|code", or with no variables in it, is sent as a
 * plain SMS.
 */
class ippanel extends \WP_SMS\Gateway
{
    public $tariff = "https://ippanel.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $has_key = true;

    private $wsdl_link = "https://edge.ippanel.com/v1/api";

    /**
     * Recipients sent in one plain request. The docs give no limit, so the
     * gateway stays at a size other Iranian panels accept.
     */
    const MAX_RECIPIENTS = 100;

    /**
     * Pattern code taken from the "message|code" text, if any.
     *
     * @var string|null
     */
    public $template_id = null;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "+989xxxxxxxxx";

        $this->gatewayFields = [
            'from'    => [
                'id'           => 'gateway_sender_id',
                'name'         => __('Sender Number', 'wp-sms'),
                'place_holder' => __('e.g., +983000505', 'wp-sms'),
                'desc'         => __('A line from your IPPanel account, for example +983000505 or 3000505.', 'wp-sms'),
            ],
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => __('API Key', 'wp-sms'),
                'desc' => __('Make it in your panel under Developers > Access Keys. The API uses this key, not the username and password you sign in with.', 'wp-sms'),
            ],
        ];

        $this->documentUrl = 'https://wsms.io/fa/docs/ippanel-gateway-setup/';

        $this->help = '
<div dir="rtl">
  <p>این درگاه برای پنل هایی است که روی IPPanel کار می کنند و به وب سرویس جدید (کلید API) منتقل شده اند.</p>
  <p><strong>کلید API:</strong> در پنل پیامک خود از بخش توسعه دهندگان ← کلیدهای دسترسی یک کلید بسازید و کپی کنید. نام کاربری و رمز عبور پنل استفاده نمی شود.</p>
  <p><strong>شماره ارسال کننده:</strong> یکی از خطوط حساب خود را وارد کنید، مثلا <code>+983000505</code> یا <code>3000505</code>.</p>
  <p><strong>ارسال با الگو (پترن):</strong></p>
  <ol>
    <li>در پنل، پترن بسازید و متغیرها را به شکل <code>%name%</code> بگذارید.<br>
      مثال: <code>کد ورود شما: %code%</code></li>
    <li>بعد از تایید پترن، در افزونه همان پیام را با متغیرهای افزونه بنویسید و بعد از <code>|</code> کد پترن را بگذارید.<br>
      مثال: <code>کد ورود شما: %code%|jhaskdab45s6f4sfw</code></li>
  </ol>
  <p>هر متغیر افزونه جای متغیر هم نام در پترن می نشیند، مثلا <code>%billing_first_name%</code> در افزونه جای <code>%billing_first_name%</code> در پترن. پیام الگو برای هر گیرنده جدا ارسال می شود.</p>
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
        $this->setTemplateIdAndMessageBody();

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

        if (!$this->hasKey()) {
            $error = $this->missingKey();
            $this->log($this->from, $this->msg, $this->to, $error->get_error_message(), 'error');

            return $error;
        }

        $from = $this->e164((string)$this->from);

        if ($from === '') {
            $error = new \WP_Error('send-sms', esc_html__('Enter your IPPanel sender number in the gateway settings.', 'wp-sms'));
            $this->log($this->from, $this->msg, $this->to, $error->get_error_message(), 'error');

            return $error;
        }

        $recipients = array_values(array_filter(array_map([$this, 'e164'], (array)$this->to)));

        if (!empty($this->template_id) && !empty($this->messageVariables) && is_array($this->messageVariables)) {
            $results = $this->sendPattern($from, $recipients);
        } else {
            $results = $this->sendPlain($from, $recipients);
        }

        if (is_wp_error($results)) {
            $this->log($this->from, $this->msg, $this->to, $results->get_error_message(), 'error');

            return $results;
        }

        $result = count($results) === 1 ? $results[0] : $results;

        // Log the result
        $this->log($this->from, $this->msg, $this->to, $result);

        /**
         * Run hook after send sms.
         *
         * @param string $result result output.
         * @since 2.4
         *
         */
        do_action('wp_sms_send', $result);

        return $result;
    }

    /**
     * Sends the text as a "webservice" send, up to MAX_RECIPIENTS per request.
     *
     * @return array[]|\WP_Error
     */
    private function sendPlain($from, $recipients)
    {
        $results = [];

        foreach (array_chunk($recipients, self::MAX_RECIPIENTS) as $chunk) {
            $data = $this->send([
                'sending_type' => 'webservice',
                'from_number'  => $from,
                'message'      => (string)$this->msg,
                'params'       => ['recipients' => $chunk],
            ]);

            if (is_wp_error($data)) {
                return $data;
            }

            $results[] = $data;
        }

        return $results;
    }

    /**
     * Sends the pattern once per number: IPPanel takes one recipient per
     * pattern request. Variables go by name, so %code% fills %code%.
     *
     * @return array[]|\WP_Error
     */
    private function sendPattern($from, $recipients)
    {
        $params = [];

        foreach ($this->messageVariables as $name => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $params[trim((string)$name, '%')] = is_scalar($value) || $value === null ? (string)$value : '';
        }

        $results = [];

        foreach ($recipients as $recipient) {
            $data = $this->send([
                'sending_type' => 'pattern',
                'from_number'  => $from,
                'code'         => (string)$this->template_id,
                'recipients'   => [$recipient],
                'params'       => (object)$params,
            ]);

            if (is_wp_error($data)) {
                return $data;
            }

            $results[] = $data;
        }

        return $results;
    }

    /**
     * Posts one send and checks that IPPanel gave it an outbox id.
     *
     * @return array|\WP_Error
     */
    private function send($body)
    {
        $data = $this->callApi('POST', '/send', $body);

        if (is_wp_error($data)) {
            return $data;
        }

        if (empty($data['message_outbox_ids'])) {
            return new \WP_Error('send-sms', esc_html__('IPPanel did not confirm the send.', 'wp-sms'));
        }

        return $data;
    }

    public function GetCredit()
    {
        if (!$this->hasKey()) {
            return $this->missingKey();
        }

        $data = $this->callApi('GET', '/payment/credit/mine');

        if (is_wp_error($data)) {
            return new \WP_Error('account-credit', $data->get_error_message());
        }

        if (!isset($data['credit']) || !is_numeric($data['credit'])) {
            return new \WP_Error('account-credit', esc_html__('IPPanel did not return a balance.', 'wp-sms'));
        }

        return $data['credit'];
    }

    /**
     * Calls the API and returns the answer's `data`, or a WP_Error that says
     * what IPPanel said.
     *
     * @return array|\WP_Error
     */
    private function callApi($method, $path, $body = null)
    {
        $args = [
            'method'  => $method,
            'timeout' => 15,
            'headers' => [
                'Authorization' => trim((string)$this->has_key),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ];

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($this->wsdl_link . $path, $args);

        if (is_wp_error($response)) {
            /* translators: %s: error message */
            return new \WP_Error('gateway-unreachable', sprintf(esc_html__('IPPanel could not be reached: %s', 'wp-sms'), $response->get_error_message()));
        }

        $http   = (int)wp_remote_retrieve_response_code($response);
        $answer = json_decode(wp_remote_retrieve_body($response), true);
        $meta   = is_array($answer) && isset($answer['meta']) && is_array($answer['meta']) ? $answer['meta'] : [];

        if ($http >= 200 && $http < 300 && isset($meta['status']) && $meta['status'] === true
            && isset($answer['data']) && is_array($answer['data'])) {
            return $answer['data'];
        }

        $said = $this->saidIn($meta);
        $code = isset($meta['message_code']) && is_scalar($meta['message_code']) ? trim((string)$meta['message_code']) : '';

        if ($http === 401 || $http === 403 || $code === '400-1') {
            $text = esc_html__('IPPanel refused the API key. Make a new one in your panel under Developers > Access Keys and paste it again.', 'wp-sms');

            if ($said !== '') {
                /* translators: %s: IPPanel's own message */
                $text .= ' ' . sprintf(esc_html__('IPPanel said: %s', 'wp-sms'), esc_html($said));
            }

            return new \WP_Error('ippanel-auth', $text);
        }

        if ($said !== '') {
            /* translators: %s: IPPanel's own message */
            return new \WP_Error('ippanel-refused', sprintf(esc_html__('IPPanel refused the request: %s', 'wp-sms'), esc_html($said)));
        }

        if ($http >= 200 && $http < 300) {
            return new \WP_Error('ippanel-unreadable', esc_html__('Could not read the answer from IPPanel.', 'wp-sms'));
        }

        /* translators: %d: HTTP status */
        return new \WP_Error('ippanel-http', sprintf(esc_html__('IPPanel answered HTTP %d.', 'wp-sms'), $http));
    }

    /**
     * IPPanel's words for a refusal: each field error, else `meta.message`.
     */
    private function saidIn($meta)
    {
        $words = [];

        if (!empty($meta['errors']) && is_array($meta['errors'])) {
            array_walk_recursive($meta['errors'], function ($word) use (&$words) {
                if (is_scalar($word) && trim((string)$word) !== '') {
                    $words[] = trim((string)$word);
                }
            });
        }

        if ($words) {
            return implode(' ', array_unique($words));
        }

        return isset($meta['message']) && is_scalar($meta['message']) ? trim((string)$meta['message']) : '';
    }

    /**
     * IPPanel takes numbers in E.164 form: +989120000000, and a line as
     * +983000505. A number with no country code is read as Iranian.
     */
    private function e164($number)
    {
        $number = trim((string)$number);

        if ($number === '') {
            return '';
        }

        $plus   = strpos($number, '+') === 0;
        $digits = preg_replace('/[^0-9]/', '', $number);

        if ($digits === '') {
            return '';
        }

        if ($plus) {
            return '+' . $digits;
        }

        if (strpos($digits, '00') === 0) {
            return '+' . substr($digits, 2);
        }

        if (strpos($digits, '98') === 0) {
            return '+' . $digits;
        }

        if (strpos($digits, '0') === 0) {
            return '+98' . substr($digits, 1);
        }

        return '+98' . $digits;
    }

    private function hasKey()
    {
        return is_string($this->has_key) && trim($this->has_key) !== '';
    }

    protected function missingKey()
    {
        return new \WP_Error('account-credit', esc_html__('Enter your IPPanel API key. The username and password are not used.', 'wp-sms'));
    }
}
