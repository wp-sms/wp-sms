<?php

namespace WP_SMS\Gateway;

class smsto extends \WP_SMS\Gateway
{
    public $wsdl_link = "https://api.sms.to";
    public $tariff = "https://auth.sms.to/";
    public $unitrial = true;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    public $callback_url;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "XXXXXXXX,YYYYYYYY";
        $this->has_key        = true;
        $this->bulk_send      = true;
        $this->help           = 'Please enter your API key and leave the API username & API password empty.';
        $this->documentUrl    = 'https://wp-sms-pro.com/resources/sms-to-gateway-configuration/';
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
        $this->msg = apply_filters('wp_sms_msg', $this->msg, $this->to);

        if (empty($this->has_key)) {
            return [
                'error'  => true,
                'reason' => 'Invalid Credentials',
                'data'   => null,
                'status' => 'FAILED'
            ];
        }

        if ($this->CountNumberOfCharacters() > 480) {
            return new \WP_Error('account-credit', 'You have exceeded the max limit of 480 characters');
        }

        $bodyContent = array(
            'sender_id' => $this->from,
            'to'        => $this->to,
            'message'   => $this->msg,
        );

        if ((isset($this->options['gateway_smsto_callback_url']))) {
            $callback_url                = apply_filters('sms_to_callback', $this->options['gateway_smsto_callback_url']);
            $bodyContent['callback_url'] = 'https://' . $callback_url . '/wp-json/sms-to/get_post';
        }

        $apiURL = "{$this->wsdl_link}/sms/send";

        if ($this->isflash) {
            $apiURL = "{$this->wsdl_link}/fsms/send";
        }

        $opts = [
            CURLOPT_URL            => $apiURL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_HTTPHEADER     => [
                'authorization: Bearer ' . $this->has_key,
                'content-type: application/json',
            ],
        ];

        if ($bodyContent) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($bodyContent);
        }

        $curlSession = curl_init();
        curl_setopt_array($curlSession, $opts);

        $response = curl_exec($curlSession);
        $err      = curl_error($curlSession);

        $response = json_decode($response);
        $err      = json_decode($err);

        curl_close($curlSession);

        if ($err) {
            $response = [
                'error'  => true,
                'reason' => $err,
                'data'   => $bodyContent,
                'status' => 'FAILED'
            ];
            do_action('wp_sms_send', $response);

            $this->log($this->from, $this->msg, $this->to, $response);

            return $response;
        }


        if (isset($response->success) && $response->success == 'true') {
            // Log the result
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
        } else {

            $errorMessage = isset($response->message) ? $response->message : print_r($response->message, true);

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $errorMessage, 'error');
            return new \WP_Error('send-sms', $errorMessage);
        }
    }

    public function GetCredit()
    {
        // Check api
        if (!$this->has_key) {
            return new \WP_Error('account-credit', 'API not set');
        }

        /**
         * Send request
         */
        $response = wp_remote_get($this->tariff . 'api/balance?api_key=' . $this->has_key);

        /**
         * Make sure the request doesn't have the error
         */
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $responseBody   = wp_remote_retrieve_body($response);
        $responseObject = json_decode($responseBody);

        /*
         * Response validity
         */
        if (wp_remote_retrieve_response_code($response) == '200') {

            if (isset($responseObject->balance)) {
                return round($responseObject->balance, 2);
            }

            return new \WP_Error('account-credit', $responseObject->message);

        } else {
            $errorResponse = isset($responseObject->message) ? $responseObject->message : $responseObject;
            return new \WP_Error('account-credit', $errorResponse);
        }
    }

    public function CountNumberOfCharacters()
    {
        return strlen($this->msg);
    }
}