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
 *   Auth: `X-API-KEY: <key>` on every request.
 *   POST /sms/v1/send/sms       {sendingNumber, recipients: [up to 100], messageText}
 *   GET  /sms/v1/account/balance -> {"data": {"balance": 9358}}
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

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxxx";

        $this->gatewayFields = [
            'from'    => [
                'id'           => 'gateway_sender_id',
                'name'         => __('Sender Number', 'wp-sms'),
                'place_holder' => __('e.g., 3000505', 'wp-sms'),
                'desc'         => __('The dedicated line bought in your Mediana account.', 'wp-sms'),
            ],
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => __('API Key', 'wp-sms'),
                'desc' => __('Find it in your Mediana panel. The API uses this key, not the username and password you sign in with.', 'wp-sms'),
            ],
        ];
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

        if (!$this->hasKey()) {
            $error = $this->missingKey();
            $this->log($this->from, $this->msg, $this->to, $error->get_error_message(), 'error');

            return $error;
        }

        if (!$this->from) {
            $error = new \WP_Error('send-sms', esc_html__('Mediana needs a sender number: the dedicated line bought in your Mediana account.', 'wp-sms'));
            $this->log($this->from, $this->msg, $this->to, $error->get_error_message(), 'error');

            return $error;
        }

        $recipients = array_values(array_filter(array_map([$this, 'localNumber'], (array)$this->to)));
        $results    = [];

        foreach (array_chunk($recipients, self::MAX_RECIPIENTS) as $chunk) {
            $data = $this->callApi('POST', '/send/sms', [
                'sendingNumber' => (string)$this->from,
                'recipients'    => $chunk,
                'messageText'   => (string)$this->msg,
            ]);

            if (is_wp_error($data)) {
                $this->log($this->from, $this->msg, $this->to, $data->get_error_message(), 'error');

                return $data;
            }

            if (empty($data['succeed']) || empty($data['requestId'])) {
                $said  = isset($data['message']) && is_scalar($data['message']) ? trim((string)$data['message']) : '';
                $error = new \WP_Error('send-sms', $said !== '' ? $said : esc_html__('Mediana did not confirm the send.', 'wp-sms'));
                $this->log($this->from, $this->msg, $this->to, $error->get_error_message(), 'error');

                return $error;
            }

            $results[] = $data;
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
     * The error codes in the description of Mediana's API collection.
     */
    private function errorText($code)
    {
        $codes = [
            '1021' => __('an unknown error occurred', 'wp-sms'),
            '1032' => __('the account has no active plan', 'wp-sms'),
            '1033' => __('the account\'s plan does not include the API', 'wp-sms'),
            '1035' => __('the account\'s plan does not include a dedicated line', 'wp-sms'),
            '1041' => __('the recipient\'s number is not valid', 'wp-sms'),
            '1042' => __('the wallet balance is not enough', 'wp-sms'),
            '1043' => __('there are more recipients than allowed', 'wp-sms'),
            '1046' => __('the request\'s parameters are not valid', 'wp-sms'),
            '1047' => __('the number is on the blacklist', 'wp-sms'),
            '1061' => __('no active line was found', 'wp-sms'),
            '1062' => __('the line cannot be used at this time of day', 'wp-sms'),
            '1074' => __('the message text is empty', 'wp-sms'),
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
