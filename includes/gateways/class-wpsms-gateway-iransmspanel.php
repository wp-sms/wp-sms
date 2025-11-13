<?php

namespace WP_SMS\Gateway;

class iransmspanel extends \WP_SMS\Gateway
{
    
    public  $version     = '2.0';
    private $base_url    = 'https://developer.persianbulk.com';
    public  $tariff      = "https://www.persianbulk.com/sms-panel";
    public  $help        = 'Persian Bulk SMS (formerly Iran SMS Panel) gateway. Get your API credentials from persianbulk.com';
    public  $documentUrl = 'https://developer.persianbulk.com/';
    public  $flash       = "enable";
    public  $unitrial    = false;
    public  $isflash     = false;
    public  $bulk_send   = true;
    public  $unit;
    public  $api_key;

    /**
     * Gateway configuration fields
     *
     * @var array
     */
    public $gatewayFields = [
        'api_key' => [
            'id'           => 'iransmspanel_api_key',
            'name'         => 'API Key',
            'type'         => 'text',
            'place_holder' => 'Enter your Persian Bulk SMS API key',
            'desc'         => 'Enter the API key provided by Persian Bulk SMS (persianbulk.com)',
        ],
        'from'    => [
            'id'           => 'gateway_sender_id',
            'name'         => 'Sender Number',
            'type'         => 'text',
            'place_holder' => 'e.g., 10001234',
            'desc'         => 'The sender number or line number provided by Persian Bulk SMS',
        ],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxxx";
    }

    /**
     * Send SMS via Persian Bulk SMS API
     *
     * @return true|\WP_Error
     */
    public function SendSMS()
    {
        /**
         * Modify sender number
         *
         * @param string $this->from sender number.
         * @since 3.4
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify receiver number
         *
         * @param array $this->to receiver number
         * @since 3.4
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this->msg text message.
         * @since 3.4
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        // Check if message is in template format: "template:TEMPLATE_ID|token:VALUE|date:VALUE"
        if (strpos($this->msg, 'template:') === 0) {
            return $this->sendTemplateMessage();
        }

        try {
            // Prepare the API endpoint
            $url = $this->base_url . '/' . $this->api_key . '/sms/SendArray';

            // Prepare the request body
            $body = [
                'receptor' => $this->to,                // Recipients as array
                'message'  => $this->msg,               // Message content
                'sender'   => $this->from,              // Sender number
            ];

            // Send the request
            $response = $this->request('POST', $url, [], $body);

            // Check if response is successful
            if (isset($response->status) && $response->status == 200) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response);

                /**
                 * Run hook after send sms.
                 *
                 * @param object $response result output.
                 * @since 2.4
                 */
                do_action('wp_sms_send', $response);

                return true;
            }

            // If we get here, something went wrong
            $error_message = isset($response->message) ? $response->message : __('Unknown error occurred', 'wp-sms');

            // Log the error
            $this->log($this->from, $this->msg, $this->to, $error_message, 'error');

            return new \WP_Error('send-sms', $error_message);

        } catch (\Exception $e) {
            // Log the error
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('send-sms', $e->getMessage());
        }
    }

    /**
     * Send Template SMS via Persian Bulk SMS API
     *
     * Supports multiple tokens in different formats:
     * - Single token: SendTemplateSMS('09123456789', 'TEMPLATE_ID', 'value')
     * - Multiple tokens as array: SendTemplateSMS('09123456789', 'TEMPLATE_ID', ['value1', 'value2', 'value3'])
     * - Additional parameters: SendTemplateSMS('09123456789', 'TEMPLATE_ID', 'value', '2025-01-15', ['token2' => 'value2', 'token3' => 'value3'])
     *
     * @param string $receptor Recipient phone number
     * @param string $template Template ID from Persian Bulk SMS panel
     * @param string|array $token Dynamic data for template variable(s). Can be string or array for multiple tokens
     * @param string|null $date Optional date data for template
     * @param array $additionalParams Optional additional parameters (e.g., ['token2' => 'value', 'token3' => 'value'])
     * @return true|\WP_Error
     */
    public function SendTemplateSMS($receptor, $template, $token, $date = null, $additionalParams = [])
    {
        try {
            // Prepare the API endpoint for template SMS
            $url = $this->base_url . '/' . $this->api_key . '/sms/Send/pattern';

            // Prepare the request body
            $body = [
                'sender'   => $receptor,                // Recipient number
                'template' => $template,                // Template ID
            ];

            // Handle token parameter - supports string or array
            if (is_array($token)) {
                // Multiple tokens provided as array
                // Try format 1: token, token2, token3, etc.
                $body['token'] = $token[0] ?? '';
                for ($i = 1; $i < count($token); $i++) {
                    $body['token' . ($i + 1)] = $token[$i];
                }
            } else {
                // Single token as string
                $body['token'] = $token;
            }

            // Add date if provided
            if ($date !== null) {
                $body['date'] = $date;
            }

            // Add any additional parameters (for flexibility)
            if (!empty($additionalParams) && is_array($additionalParams)) {
                $body = array_merge($body, $additionalParams);
            }

            // Send the request
            $response = $this->request('POST', $url, [], $body);

            // Check if response is successful
            if (isset($response->status) && $response->status == 200) {
                // Log the result
                $this->log($this->from, "Template: {$template}", [$receptor], $response);

                /**
                 * Run hook after send sms.
                 *
                 * @param object $response result output.
                 * @since 2.4
                 */
                do_action('wp_sms_send', $response);

                return true;
            }

            // If we get here, something went wrong
            $error_message = isset($response->message) ? $response->message : __('Unknown error occurred', 'wp-sms');

            // Log the error
            $this->log($this->from, "Template: {$template}", [$receptor], $error_message, 'error');

            return new \WP_Error('send-template-sms', $error_message);

        } catch (\Exception $e) {
            // Log the error
            $this->log($this->from, "Template: {$template}", [$receptor], $e->getMessage(), 'error');

            return new \WP_Error('send-template-sms', $e->getMessage());
        }
    }

    /**
     * Private helper to send template message from SendSMS()
     *
     * Parses message format: "template:TEMPLATE_ID|token:VALUE|date:VALUE|token2:VALUE2|token3:VALUE3"
     * Supports multiple tokens: token, token2, token3, etc.
     *
     * @return true|\WP_Error
     */
    private function sendTemplateMessage()
    {
        try {
            // Parse the message format: "template:TEMPLATE_ID|token:VALUE|date:VALUE|token2:VALUE2"
            $parts = explode('|', $this->msg);
            $data = [];

            foreach ($parts as $part) {
                $keyValue = explode(':', $part, 2);
                if (count($keyValue) === 2) {
                    $data[trim($keyValue[0])] = trim($keyValue[1]);
                }
            }

            // Validate required fields
            if (!isset($data['template']) || !isset($data['token'])) {
                return new \WP_Error('send-template-sms', __('Template format requires template and token parameters', 'wp-sms'));
            }

            // Prepare the API endpoint for template SMS
            $url = $this->base_url . '/' . $this->api_key . '/sms/Send/pattern';

            // Use first recipient for template SMS (templates typically go to single recipient)
            $receptor = is_array($this->to) ? $this->to[0] : $this->to;

            // Prepare the request body
            $body = [
                'sender'   => $receptor,
                'template' => $data['template'],
                'token'    => $data['token'],
            ];

            // Add date if provided
            if (isset($data['date'])) {
                $body['date'] = $data['date'];
            }

            // Add additional token parameters (token2, token3, token4, etc.)
            foreach ($data as $key => $value) {
                // Skip already added parameters
                if (!in_array($key, ['template', 'token', 'date', 'sender'])) {
                    $body[$key] = $value;
                }
            }

            // Send the request
            $response = $this->request('POST', $url, [], $body);

            // Check if response is successful
            if (isset($response->status) && $response->status == 200) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response);

                /**
                 * Run hook after send sms.
                 *
                 * @param object $response result output.
                 * @since 2.4
                 */
                do_action('wp_sms_send', $response);

                return true;
            }

            // If we get here, something went wrong
            $error_message = isset($response->message) ? $response->message : __('Unknown error occurred', 'wp-sms');

            // Log the error
            $this->log($this->from, $this->msg, $this->to, $error_message, 'error');

            return new \WP_Error('send-template-sms', $error_message);

        } catch (\Exception $e) {
            // Log the error
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('send-template-sms', $e->getMessage());
        }
    }

    /**
     * Get account credit/balance
     *
     * @return float|int|\WP_Error
     */
    public function GetCredit()
    {
        // Check API key
        if (!$this->api_key) {
            return new \WP_Error('account-credit', esc_html__('API Key is required.', 'wp-sms'));
        }

        try {
            // Prepare the API endpoint
            $url = $this->base_url . '/' . $this->api_key . '/sms/Balance';

            // Send the request
            $response = $this->request('GET', $url);

            // Check if response is successful
            if (isset($response->status) && $response->status == 200) {
                // Return the balance/credit
                if (isset($response->data->balance)) {
                    return floatval($response->data->balance);
                } elseif (isset($response->balance)) {
                    return floatval($response->balance);
                }
            }

            // If we get here, something went wrong
            $error_message = isset($response->message) ? $response->message : __('Could not retrieve account credit', 'wp-sms');

            return new \WP_Error('account-credit', $error_message);

        } catch (\Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }
    }
}
