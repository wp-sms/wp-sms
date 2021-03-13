<?php

namespace WP_SMS\Gateway;

class gatewayapi extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://gatewayapi.com/rest";
    public $tariff = "https://gatewayapi.com";
    public $has_key = true;
    public $unit;
    public $unitrial = true;
    public $flash = "disable";
    public $isflash = false;
    protected $accountBalance = null;
    public $help = 'All you need is the API Token available from the <a href="https://gatewayapi.com/app" target="_blank">GatewayAPI Dashboard &rarr;</a>.<br>Just leave the username and password fields blank.';

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "45xxxxxxxx or 49xxxxxxxxxxx";
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

        // Get the credit.
        $credit = $this->GetCredit();

        // Check gateway credit
        if (is_wp_error($credit)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return $credit;
        }

        /**
         * Construct recipients array
         *
         * @param array $recipients recipients array
         */
        $recipients = [];

        foreach ($this->to as $index => $number) {
            $recipients[] = array('msisdn' => $number);
        }


        $payload = array(
            'sender'     => $this->from,
            'message'    => $this->msg,
            'recipients' => $recipients
        );

        if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
            $this->msg = bin2hex(mb_convert_encoding($this->msg, 'UCS-2', 'auto'));
            $payload   = array(
                'sender'     => $this->from,
                'message'    => $this->msg,
                'recipients' => $recipients,
                'encoding'   => 'UTF-16-BE'
            );
        }

        if ($this->isflash) {
            $payload['destaddr'] = 'DISPLAY';
        }

        /**
         * Send SMS with POST request as JSON
         */
        $res = wp_remote_request($this->wsdl_link . '/mtsms', [
            'method'  => 'POST',
            'body'    => json_encode($payload),
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("$this->has_key:"),
                'Accept'        => 'application/json, text/javascript',
                'Content-Type'  => 'application/json'
            )
        ]);

        if (is_wp_error($res)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $res, 'error');

            return $res;
        }

        // Decode the response body
        $responseBody = json_decode($res['body']);

        // Check of send was successful
        if ($res['response']['code'] === 200) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $responseBody);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $responseBody);

            return 200; // 200 OK
        } else if ($res['response']['code'] >= 500) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $res['body'] ?: 'An unexpected error occurred.', 'error');

            return new \WP_Error('send-sms', $res['body'] ?: 'An unexpected error occurred.');
        }
        // Log the result
        $this->log($this->from, $this->msg, $this->to, $this->formatErrorMessage($responseBody), 'error');

        // Return error and format error message from the API to the client
        return new \WP_Error('send-sms', $this->formatErrorMessage($responseBody));
    }

    /**
     * Get formatted credit string.
     *
     * @return string
     */
    public function GetCredit()
    {
        return $this->balance()->credit . " " . $this->balance()->currency;
    }

    /**
     * Check if client has credits.
     *
     * @return boolean
     */
    public function hasCredit()
    {
        return $this->balance()->credit > 0;
    }

    /**
     * Retrive and cache the account balance for the client.
     *
     * @return object
     */
    public function balance()
    {
        if (is_null($this->accountBalance)) {
            $res = wp_remote_request($this->wsdl_link . '/me', [
                'method'  => 'GET',
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode("$this->has_key:")
                ]
            ]);

            $responseBody = json_decode($res['body']);

            if ($res['response']['code'] === 200) {
                $this->accountBalance = $responseBody;
            } else {
                // Mock the account balance object
                $this->accountBalance = (object)['credit' => 0, 'currency' => ''];
            }
        }

        return $this->accountBalance;
    }

    /**
     * Format the error message from the API.
     *
     * @param object $error
     *
     * @return string
     */
    public function formatErrorMessage($error)
    {
        if (!$error->variables) {
            return $error->message;
        }

        $message = $error->message;

        foreach ($error->variables as $index => $value) {
            $placeholder = $index + 1;

            $message = str_replace("%{$placeholder}", $value, $message);
        }

        return $message;
    }
}