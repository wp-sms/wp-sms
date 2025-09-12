<?php

namespace WP_SMS\Gateway;

use WP_Error;
use WP_SMS\Gateway;

class melipayamak extends Gateway
{
    protected $wsdl_link        = 'https://rest.payamak-panel.com/api/';
    public $tariff              = "http://melipayamak.ir/";
    public $unitrial            = true;
    public $unit;
    public $flash               = "enable";
    public $isflash             = false;
    public $from_support_one    = '';
    public $from_support_two    = '';
    public $documentUrl         = "https://www.melipayamak.com/lab/wordpress-sending-sms-plugin-wp-smsn/";

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";

        $this->gatewayFields = [
            'username'         => [
                'id'   => 'gateway_username',
                'name' => __('API username', 'wp-sms'),
                'desc' => __('Enter API username of gateway', 'wp-sms'),
            ],
            'password'         => [
                'id'   => 'gateway_password',
                'name' => __('API password', 'wp-sms'),
                'desc' => __('Enter API password of gateway', 'wp-sms'),
            ],
            'from'             => [
                'id'   => 'gateway_sender_id',
                'name' => __('Sender number', 'wp-sms'),
                'desc' => __('Sender number or sender ID', 'wp-sms'),
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
    }

    public function SendSMS()
    {
        // Check gateway credit
        if (is_wp_error($this->GetCredit())) {
            return new \WP_Error('account-credit',
                esc_html__('Your account does not credit for sending sms.', 'wp-sms'));
        }

        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         *
         * @since 3.4
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         *
         * @since 3.4
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         *
         * @since 3.4
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        // Parse message and determine API type
        $parsed_message = $this->parseMessageAndApiType($this->msg);
        $effective_api_type = $parsed_message['api_type'];
        $body_id = $parsed_message['body_id'];
        $formatted_text = $parsed_message['formatted_text'];
       
        try {
            if ($effective_api_type === 'shared') {
                return $this->sendSharedApiSms($formatted_text, $body_id);
            }

            if ($effective_api_type === 'smart') {
                return $this->sendSmartApiSms($formatted_text);
            }

            // Default API (regular SMS)
            return $this->sendDefaultApiSms($formatted_text);
        } catch (\Exception $ex) {
            $this->log($this->from, $this->msg, $this->to, $ex->getMessage(), 'error');
            return new WP_Error('send-sms', $ex->getMessage());
        }
    }

    /**
     * Parse message and determine API type, body ID, and formatted text
     *
     * @param string $message The message to parse
     * @return array Array containing api_type, body_id, and formatted_text
     */
    public function parseMessageAndApiType($message)
    {
        // Parse message and API type
        $parts = explode("##", $message, 2);
        $raw_message = isset($parts[0]) ? $parts[0] : '';
        $api_type_override = isset($parts[1]) ? $parts[1] : null;

        $body_id = null;
        $message_text = $raw_message;
        $formatted_text = $raw_message;

        // If override is 'smart', don't extract templates - send whole message as-is
        if ($api_type_override === 'smart') {
            return [
                'api_type' => 'smart',
                'body_id' => null,
                'message' => $raw_message,
                'formatted_text' => $raw_message
            ];
        }

        // Check for template pattern: message|templateId (in the raw message part)
        $template_parts = explode("|", $raw_message, 2);
        if (isset($template_parts[1]) && $template_parts[1]) {
            $body_id = trim($template_parts[1]);
            $message_text = trim($template_parts[0]);
            
            // For the |templateId pattern, use the message as-is for smart API
            // For shared API, we need to format it properly
            if ($api_type_override === 'shared' || (!$api_type_override && $body_id)) {
                // Split by colon and join with semicolon for shared API
                $args = $this->parseArgsFromMessage($message_text, ':');
                if ($args && count($args) > 1) {
                    $formatted_text = implode(';', $args);
                } else {
                    $formatted_text = $message_text;
                }
            } else {
                $formatted_text = $message_text;
            }
        } else {
            // Check for pattern: templateId-args:args2:args3 (only if it looks like a real template)
            // A real template should have numeric template ID and colon-separated arguments
            if (preg_match('/^(\d+)-(.+)$/', $raw_message, $matches)) {
                $potential_template_id = $matches[1];
                $potential_args = $matches[2];
                
                // Treat as template if:
                // 1. Args contain colons (indicating multiple parameters), OR
                // 2. API type is explicitly set to 'shared' (indicating user wants template mode)
                if (strpos($potential_args, ':') !== false || $api_type_override === 'shared') {
                    $body_id = $potential_template_id;
                    $message_text = $potential_args;
                    
                    // Split args by colon and join with semicolon for shared API format
                    $args = $this->parseArgsFromMessage($potential_args, ':');
                    if ($args && count($args) > 1) {
                        $formatted_text = implode(';', $args);
                    } else {
                        $formatted_text = $potential_args;
                    }
                }
            }
        }

        // Determine effective API type
        $effective_api_type = 'smart'; // default
        if ($api_type_override) {
            $effective_api_type = $api_type_override;
        } elseif ($body_id !== null) {
            $effective_api_type = 'shared';
        }

        // If override is not 'shared' or 'smart', preserve the original message with override
        if ($api_type_override && $api_type_override !== 'shared' && $api_type_override !== 'smart') {
            $formatted_text = $message; // Keep the original message with ##override
        }

        return [
            'api_type' => $effective_api_type,
            'body_id' => $body_id,
            'message' => $message_text,
            'formatted_text' => $formatted_text
        ];
    }

    /**
     * Send SMS using Shared API
     *
     * @param string $formatted_text The formatted text to send
     * @param string $body_id The template body ID
     * @return mixed Response from the API
     */
    private function sendSharedApiSms($formatted_text, $body_id)
    {
        $responses = [];
        foreach ($this->to as $recipient) {
            $response = $this->request('POST', $this->wsdl_link . 'SendSMS/BaseServiceNumber', [], [
                'headers'   => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body'      => [
                    'username' => $this->username,
                    'password' => $this->password,
                    'text'     => $formatted_text,
                    'to'       => $recipient,
                    'bodyId'   => $body_id,
                ],
                'timeout'   => 20,
                'sslverify' => false,
            ]);

            $this->log($this->from, $this->msg, $recipient, $response);
            do_action('wp_sms_send', $response);
            
            $responses[] = $response;
        }
        
        // Return the last response for compatibility
        return end($responses);
    }

    /**
     * Send SMS using Smart API
     *
     * @param string $formatted_text The formatted text to send
     * @return mixed Response from the API
     */
    private function sendSmartApiSms($formatted_text)
    {
        $recipients = is_array($this->to) ? implode(',', $this->to) : $this->to;
        $response   = $this->request('POST', $this->wsdl_link . 'SmartSMS/Send', [], [
            'headers'   => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'      => [
                'username'       => $this->username,
                'password'       => $this->password,
                'from'           => $this->from,
                'to'             => $recipients,
                'text'           => $formatted_text,
                'fromSupportOne' => $this->from_support_one,
                'fromSupportTwo' => $this->from_support_two,
            ],
            'timeout'   => 20,
            'sslverify' => false,
        ]);

        $this->log($this->from, $this->msg, $this->to, $response);
        do_action('wp_sms_send', $response);

        return $response;
    }

    /**
     * Send SMS using Default API
     *
     * @param string $formatted_text The formatted text to send
     * @return mixed Response from the API
     */
    private function sendDefaultApiSms($formatted_text)
    {
        $recipients = is_array($this->to) ? implode(',', $this->to) : $this->to;
        $response   = $this->request('POST', $this->wsdl_link . 'SendSMS/SendSMS', [], [
            'headers'   => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'      => [
                'username' => $this->username,
                'password' => $this->password,
                'from'     => $this->from,
                'to'       => $recipients,
                'text'     => $formatted_text,
                'isflash'  => $this->isflash ? 'true' : 'false',
                'udh'      => '',
                'recId'    => '0',
                'status'   => '0',
            ],
            'timeout'   => 20,
            'sslverify' => false,
        ]);

        $this->log($this->from, $this->msg, $this->to, $response);
        do_action('wp_sms_send', $response);

        return $response;
    }

    public function GetCredit()
    {
        try {
            $response = $this->request('POST', $this->wsdl_link . 'SendSMS/GetCredit', [], [
                'headers'   => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body'      => [
                    'username' => $this->username,
                    'password' => $this->password,
                ],
                'timeout'   => 20,
                'sslverify' => false,
            ]);

            if (isset($response->RetStatus) && $response->RetStatus == 1) {
                return $response->Value;
            }

            return new WP_Error('account-credit', __('Failed to retrieve credit from MeliPayamak.', 'wp-sms'));
        } catch (\Exception $ex) {
            return new WP_Error('account-credit', $ex->getMessage());
        }
    }

    /**
     * Parse arguments from a message string using a specified separator.
     *
     * @param string $message The message to parse
     * @param string $separator The separator character (default: ":")
     * @return array|null Array of arguments or null if parsing fails
     */
    private function parseArgsFromMessage($message, $separator = ":")
    {
        $message_body = explode($separator, $message);

        if (is_array($message_body)) {
            return $message_body;
        }

        return null;
    }
}
