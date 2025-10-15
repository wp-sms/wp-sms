<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class smses extends Gateway
{
    /**
     * API Base URL.
     *
     * @var string
     */
    private $wsdlLink = "http://194.0.137.110:32161/bulk/";

    /**
     * Pricing page URL.
     *
     * @var string
     */
    public $tariff = "https://sms.es/";

    /**
     * Determines how the account balance unit is represented.
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
            return new WP_Error('missing-credentials', __('API Username and API Password are required.', 'wp-sms'));
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
                throw new Exception($response->error->message);
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
        $receivers = $this->to;
        $responses = [];

        foreach ($receivers as $receiver) {
            $body = [
                'type'     => 'text',
                'auth'     => [
                    'username' => $this->username,
                    'password' => $this->password,
                ],
                'sender'   => $this->from,
                'receiver' => $receiver,
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

            $responses[] = $this->request('POST', $this->wsdlLink . 'sendsms', [], $params);
        }

        return end($responses);
    }
}