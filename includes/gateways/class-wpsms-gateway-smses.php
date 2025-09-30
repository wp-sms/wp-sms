<?php

namespace WP_SMS\Gateway;

use WP_SMS\Gateway;
use Exception;
use WP_Error;

class smses extends Gateway
{
    /**
     * API Base URL.
     *
     * @var string
     */
    private $wsdl_link = "http://194.0.137.110:32161/bulk/";

    /**
     * Pricing page URL.
     *
     * @var string
     */
    public $tariff = "https://sms.es/";

    /**
     * Whether trial credit is supported.
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
     * Whether the incoming message is supported
     *
     * @var bool
     */
    public $supportIncoming = true;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Send SMS message.
     *
     * @return object|WP_Error Response object on success, WP_Error on failure.
     */
    public function SendSMS()
    {
        if (empty($this->username) || empty($this->password)) {
            return new WP_Error('missing-credentials', __('Username and Password are required.', 'wp-sms'));
        }

        // Filters for customization.
        $this->from = apply_filters('wp_sms_from', $this->from);
        $this->to   = apply_filters('wp_sms_to', $this->to);
        $this->msg  = apply_filters('wp_sms_msg', $this->msg);

        try {
            $response = $this->sendSimpleSMS();

            if (is_wp_error($response)) {
                $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');
                return $response;
            }

            if (!empty($response->error)) {
                return new WP_Error('send-sms-error', __('Failed to send SMS.', 'wp-sms'));
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
     * @return null
     */
    public function GetCredit()
    {
        return null;
    }

    /**
     * Send a simple SMS message.
     *
     * @return object API response object.
     * @throws Exception If request fails.
     */
    private function sendSimpleSMS()
    {
        $responses = [];

        foreach ($this->to as $number) {
            $body = [
                'type'     => 'text',
                'auth'     => [
                    'username' => $this->username,
                    'password' => $this->password,
                ],
                'sender'   => $this->from,
                'receiver' => $this->formatReceiverNumber($number),
                'text'     => $this->msg,
                'dcs'      => 'gsm',
            ];

            if (isset($this->options['send_unicode']) && $this->options['send_unicode']) {
                $body['dcs'] = 'ucs';
            }

            if ($this->isflash) {
                $body['flash'] = $this->isflash;
            }

            $params = [
                'headers' => array(
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ),
                'body'    => json_encode($body),
            ];

            $responses[$number] = $this->request('POST', $this->wsdl_link . 'sendsms', [], $params);
        }

        return end($responses);
    }

    /**
     * Format a recipient phone number for SMS in numeric E.164 format.
     *
     * @param string $number
     *
     * @return string
     */
    private function formatReceiverNumber($number)
    {
        return preg_replace('/\D+/', '', $number);
    }
}