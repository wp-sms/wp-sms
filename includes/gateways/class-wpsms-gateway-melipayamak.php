<?php

namespace WP_SMS\Gateway;

use WP_Error;
use WP_SMS\Gateway;

class melipayamak extends Gateway
{
    public $tariff = "http://melipayamak.ir/";
    public $unitrial = true;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    public string $from_support_one = '';
    public string $from_support_two = '';

    protected string $base_url = 'https://rest.payamak-panel.com/api/';

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";

        // Define configuration fields for the gateway
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
            return new \WP_Error('account-credit', esc_html__('Your account does not credit for sending sms.', 'wp-sms'));
        }

        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         * @since 3.4
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         * @since 3.4
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         * @since 3.4
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        // Try to extract method (shared or smart) from the text message
        [$raw_message, $api_type_override] = array_pad(explode("##", $this->msg, 2), 2, null);

        // Attempt to extract bodyId and message body
        $body_id = null;
        $message = $raw_message;

        $template_data = $this->getTemplateIdAndMessageBody();

        if (is_array($template_data)) {
            $body_id = $template_data['template_id'];
            $message = $template_data['message'];
        } elseif (str_contains($message, '-')) {
            [$body_id, $message] = explode('-', $message, 2);
        }

        // Default message fallback
        $this->msg = $message ?? $this->msg;

        // Format pattern arguments if present
        $pattern_values = $this->getArgsFromPatternedMessages();
        $formatted_text = $pattern_values ? implode(';', $pattern_values) : $message;

        // Decide API type
        $effective_api_type = $api_type_override ?? ($body_id ? 'shared' : 'smart');

        //set the message to original for better logging
        $this->msg = $raw_message;


        try {
            // Shared SMS block
            if ($effective_api_type === 'shared') {
                foreach ($this->to as $recipient) {
                    $response = $this->request('POST', $this->base_url . 'SendSMS/BaseServiceNumber', [], [
                        'headers'   => ['Content-Type' => 'application/x-www-form-urlencoded'],
                        'body'      => http_build_query([
                            'username' => $this->username,
                            'password' => $this->password,
                            'text'     => $formatted_text,
                            'to'       => $recipient,
                            'bodyId'   => $body_id,
                        ]),
                        'timeout'   => 20,
                        'sslverify' => false,
                    ]);

                    $this->log($this->from, $this->msg, $recipient, $response);
                    do_action('wp_sms_send', $response);
                    return $response;
                }
            }

            // Smart SMS block
            if ($effective_api_type === 'smart') {
                $recipients = is_array($this->to) ? implode(',', $this->to) : $this->to;
                $response = $this->request('POST', $this->base_url . 'SmartSMS/Send', [], [
                    'headers'   => ['Content-Type' => 'application/x-www-form-urlencoded'],
                    'body'      => http_build_query([
                        'username'       => $this->username,
                        'password'       => $this->password,
                        'from'           => $this->from,
                        'to'             => $recipients,
                        'text'           => $formatted_text,
                        'fromSupportOne' => $this->from_support_one,
                        'fromSupportTwo' => $this->from_support_two,
                    ]),
                    'timeout'   => 20,
                    'sslverify' => false,
                ]);

                $this->log($this->from, $this->msg, $this->to, $response);
                do_action('wp_sms_send', $response);
                return $response;
            }

            // Default SMS block
            $recipients = is_array($this->to) ? implode(',', $this->to) : $this->to;

            $response = $this->request('POST', $this->base_url . 'SendSMS/SendSMS', [], [
                'headers'   => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body'      => http_build_query([
                    'username' => $this->username,
                    'password' => $this->password,
                    'from'     => $this->from,
                    'to'       => $recipients,
                    'text'     => $formatted_text,
                    'isflash'  => $this->isflash ? 'true' : 'false',
                    'udh'      => '',
                    'recId'    => '0',
                    'status'   => '0',
                ]),
                'timeout'   => 20,
                'sslverify' => false,
            ]);

            $this->log($this->from, $this->msg, $this->to, $response);
            do_action('wp_sms_send', $response);
            return $response;

        } catch (\Throwable $ex) {
            $this->log($this->from, $this->msg, $this->to, $ex->getMessage(), 'error');
            return new WP_Error('send-sms', $ex->getMessage());
        }
    }

    public function GetCredit()
    {
        try {
            $response = $this->request('POST', $this->base_url . 'SendSMS/GetCredit', [], [
                'headers'   => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body'      => http_build_query([
                    'username' => $this->username,
                    'password' => $this->password,
                ]),
                'timeout'   => 20,
                'sslverify' => false,
            ]);

            if (isset($response->RetStatus) && $response->RetStatus == 1) {
                return $response->Value;
            }

            return new WP_Error('account-credit', __('Failed to retrieve credit from MeliPayamak.', 'wp-sms'));
        } catch (\Throwable $ex) {
            return new WP_Error('account-credit', $ex->getMessage());
        }
    }
}
