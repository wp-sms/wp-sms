<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class melipayamak extends Gateway
{
    /**
     * API Base URL.
     *
     * @var string
     */
    private $wsdl_link = "https://rest.payamak-panel.com/api/";

    /**
     * Pricing page URL.
     *
     * @var string
     */
    public $tariff = "https://www.melipayamak.com/price/";

    /**
     * Whether trial credit is supported.
     *
     * @var bool
     */
    public $unitrial = true;

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
     * Template ID for Service-Line API.
     *
     * @var string
     */
    public $template_id = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->gatewayFields = [
            'username' => [
                'id'   => 'gateway_username',
                'name' => __('API Username', 'wp-sms'),
                'desc' => __('Enter the API username provided by your SMS gateway.', 'wp-sms'),
            ],
            'password' => [
                'id'   => 'gateway_password',
                'name' => __('API Password', 'wp-sms'),
                'desc' => __('Enter the API password provided by your SMS gateway.', 'wp-sms'),
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => __('Sender Number', 'wp-sms'),
                'desc' => __('Enter the sender number or sender ID registered with your SMS gateway.', 'wp-sms'),
            ],
        ];
    }

    /**
     * Sets the template ID based on the current message.
     *
     * @return void
     */
    public function setTemplateIdAndMessageBody()
    {
        $templateData = $this->getTemplateIdAndMessageBody();

        if (!empty($templateData['template_id'])) {
            $this->template_id = $templateData['template_id'];
        }

        if (!empty($templateData['message'])) {
            $this->msg = $templateData['message'];
        }
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

        $credit = $this->GetCredit();
        if (is_wp_error($credit)) {
            return $credit;
        }

        $this->setTemplateIdAndMessageBody();

        // Filters for customization.
        $this->from = apply_filters('wp_sms_from', $this->from);
        $this->to   = apply_filters('wp_sms_to', $this->to);
        $this->msg  = apply_filters('wp_sms_msg', $this->msg);

        try {
            if (!empty($this->template_id) && !empty($this->messageVariables)) {
                $response = $this->sendTemplateSMS();
            } else {
                $response = $this->sendSimpleSMS();
            }
            
            if (is_wp_error($response)) {
                $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');
                return $response;
            }

            if (!isset($response->RetStatus) && $response->RetStatus !== 1) {
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
     * @return float|WP_Error Balance amount on success, WP_Error on failure.
     */
    public function GetCredit()
    {
        try {
            $body = [
                'username' => $this->username,
                'password' => $this->password,
            ];

            $params = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body'    => $body,
            ];

            $response = $this->request('POST', $this->wsdl_link . 'SendSMS/GetCredit', [], $params);

            if (isset($response->RetStatus) && $response->RetStatus == 1) {
                return $response->Value;
            }

            return new WP_Error('account-credit-error', __('Failed to retrieve credit.', 'wp-sms'));
        } catch (Exception $e) {
            return new WP_Error('account-credit-error', $e->getMessage());
        }
    }

    /**
     * Send a simple SMS message.
     *
     * @return object API response object.
     * @throws Exception If request fails.
     */
    private function sendSimpleSMS()
    {
        $recipients          = $this->formatReceiverNumbers($this->to);
        $recipientsFormatted = count($recipients) > 1 ? implode(',', $recipients) : $recipients[0];
        $body                = [
            'username' => $this->username,
            'password' => $this->password,
            'from'     => $this->from,
            'to'       => $recipientsFormatted,
            'text'     => $this->msg,
        ];

        if ($this->isflash) {
            $body['isflash'] = $this->isflash;
        }

        $params = [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => $body,
        ];

        return $this->request('POST', $this->wsdl_link . 'SendSMS/SendSMS', [], $params);
    }

    /**
     * Send SMS using template-based API (Service-Line).
     *
     * @return object|WP_Error API response object, or WP_Error on failure.
     * @throws Exception If request fails.
     */
    private function sendTemplateSMS()
    {
        if (empty($this->messageVariables)) {
            return new WP_Error('invalid-template', __('Message does not contain valid template placeholders.', 'wp-sms'));
        }

        $messageValues = array_values($this->messageVariables);

        $body = [
            'username' => $this->username,
            'password' => $this->password,
            'text'     => implode(';', $messageValues),
            'to'       => $this->formatReceiverNumbers($this->to)[0],
            'bodyId'   => (int)$this->template_id,
        ];

        $params = [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => $body,
        ];

        return $this->request('POST', $this->wsdl_link . 'SendSMS/BaseServiceNumber', [], $params);
    }

    /**
     * Format receiver phone numbers.
     *
     * Ensures numbers are in local `09xxxxxxxxx` format.
     *
     * @param array|string $numbers Phone number(s).
     * @return array Formatted phone numbers.
     */
    private function formatReceiverNumbers($numbers)
    {
        if (!is_array($numbers)) {
            $numbers = [$numbers];
        }

        $formatted = [];
        foreach ($numbers as $number) {
            $clean = preg_replace('/\D+/', '', $number);

            if (substr($clean, 0, 2) === '98') {
                $formatted[] = '0' . substr($clean, 2);
            } elseif (strlen($clean) === 10 && substr($clean, 0, 1) === '9') {
                $formatted[] = '0' . $clean;
            } else {
                $formatted[] = $clean;
            }
        }

        return $formatted;
    }
}