<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class threema extends Gateway
{
    const API_URL = "https://msgapi.threema.ch/send_simple";

    public $tariff = 'https://threema.ch/en/gateway';
    public $unitrial = false;
    public $unit;
    public $help = 'Enter your Threema Gateway credentials. The recipient must be a valid Threema ID.';
    public $supportMedia = false;
    public $supportIncoming = false;

    public function __construct()
    {
        parent::__construct();

        $this->gatewayFields = [
            'username' => [
                'id'           => 'gateway_username',
                'name'         => 'Threema Gateway ID',
                'place_holder' => 'e.g., AB1C2DE3',
                'desc'         => 'Enter your Threema Gateway ID.',
            ],
            'password' => [
                'id'           => 'gateway_password',
                'name'         => 'Secret',
                'place_holder' => 'Your Threema secret',
                'desc'         => 'Enter your Gateway secret.',
            ],
            'from' => [
                'id'           => 'gateway_sender_id',
                'name'         => 'Sender Threema ID (optional)',
                'place_holder' => 'e.g., MYGATEID',
                'desc'         => 'Sender Threema ID if applicable.',
            ],
        ];

        $this->bulk_send = false;
    }

    public function SendSMS()
    {
        $this->from = apply_filters('wp_sms_from', $this->from);
        $this->to = apply_filters('wp_sms_to', $this->to);
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {
            if (empty($this->username) || empty($this->password)) {
                throw new Exception(__('Threema Gateway credentials not set.', 'wp-sms-pro'));
            }

            foreach ($this->to as $recipient) {
                $params = [
                    'from'    => $this->from ?: $this->username,
                    'to'      => $recipient,
                    'text'    => $this->msg,
                ];

                $headers = [
                    'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ];

                $response = $this->request('POST', self::API_URL, ['headers' => $headers], $params);

                if (!isset($response->body) || !str_starts_with((string) $response->status_code, '2')) {
                    throw new Exception('Threema send failed. Response: ' . print_r($response, true));
                }

                $this->log($this->from, $this->msg, [$recipient], $response);
            }

            do_action('wp_sms_send', $response);

            return $response;

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        return __('Not available for Threema', 'wp-sms');
    }
}
