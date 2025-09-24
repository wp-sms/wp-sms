<?php

namespace WP_SMS\Gateway;

use WP_SMS\Gateway;
use Exception;
use WP_Error;

class smses extends Gateway
{
    /**
     * Base URL or web service endpoint for sending API requests.
     *
     * @var string
     */
    private $wsdl_link = "http://194.0.137.110:32161/bulk/";

    /**
     * The website or tariff page of the SMS service provider.
     *
     * @var string
     */
    public $tariff = "https://sms.es/";

    /**
     * Indicates whether the service provides a trial (test) mode.
     * If true, you can test the service without purchasing credits.
     *
     * @var bool
     */
    public $unitrial = false;

    /**
     * The unit of SMS credit (e.g., "SMS" or "credit").
     * This is usually set after receiving a response from the API.
     *
     * @var string|null
     */
    public $unit;

    /**
     * Indicates whether the service supports sending Flash SMS.
     * Can be "enable" or "disable".
     *
     * @var string
     */
    public $flash = "enable";

    /**
     * Determines if the current message should be sent as a Flash SMS.
     * Default is false, meaning messages are sent as normal SMS.
     *
     * @var bool
     */
    public $isflash = false;

    /**
     * Whether bulk SMS sending is supported
     *
     * @var bool
     */
    public $bulk_send = false;

    /**
     * smses constructor.
     *
     * Calls the parent Gateway constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Send an SMS message.
     *
     * @return string|WP_Error
     *
     * @since 1.0
     */
    public function SendSMS()
    {
        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         *
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         *
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         *
         * @since 3.4
         *
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {
            return $this->sendDefaultApiSms();
        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    /**
     * Send SMS using the default API endpoint.
     *
     * @return string|WP_Error
     *
     * @throws Exception
     */
    private function sendDefaultApiSms()
    {
        $body = [
            'type'     => 'text',
            'auth'     => [
                'username' => $this->username,
                'password' => $this->password,
            ],
            'sender'   => $this->from,
            'receiver' => $this->formatReceiverNumber($this->to[0]),
            'text'     => $this->msg,
            'dcs'      => 'gsm',
        ];

        if (isset($this->options['send_unicode']) && $this->options['send_unicode']) {
            $body['dcs'] = 'ucs';
        }

        if ($this->isflash) {
            $body['flash'] = $this->isflash;
        }

        $args = [
            'headers' => array(
                'content-type' => 'application/json'
            ),
            'body'    => json_encode($body),
        ];

        $response = $this->request('POST', $this->wsdl_link . 'sendsms', [], $args);

        //log the result
        $this->log($this->from, $this->msg, $this->to, $response);

        /**
         * Run hook after send sms.
         *
         * @param string $response result output.
         * @since 2.4
         *
         */
        do_action('wp_sms_send', $response);

        return $response;
    }

    /**
     * Retrieve account credit balance from the SMS service provider.
     *
     * @return null
     */
    public function GetCredit()
    {
        return null;
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