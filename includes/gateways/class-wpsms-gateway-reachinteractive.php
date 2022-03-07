<?php

namespace WP_SMS\Gateway;

class reachinteractive extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://api.reach-interactive.com/sms";
    public $tariff = "https://reach-interactive.com/";
    public $documentUrl = 'https://wp-sms-pro.com/resources/reach-interactive-gateway-configuration/';
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    private $totalPerSecond = 50;
    private $waitingSeconds = 1;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "The phone number(s) the message should be sent to (must be in international format, like 447xxxxxxxxx).";
    }

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

        // Get the credit.
        $credit = $this->GetCredit();

        // Check gateway credit
        if (is_wp_error($credit)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return $credit;
        }

        $separatedNumbers = array_chunk($this->to, $this->totalPerSecond);
        $response         = [];

        foreach ($separatedNumbers as $numbers) {
            $response = $this->send($numbers);
        }

        if (is_wp_error($response)) {
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');
            return $response;
        }

        /**
         * Run hook after send sms.
         *
         * @param string $response result output.
         *
         * @since 2.4
         *
         */
        do_action('wp_sms_send', $response);

        return $response;
    }

    /**
     * send SMS private method
     *
     * @param $numbers
     *
     * @return array|\WP_Error
     */
    private function send($numbers)
    {
        $encoding = 1;
        if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
            $encoding = 2;
        }

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'username'     => $this->username,
                'password'     => $this->password,
            ),
            'body'    => json_encode(array(
                'to'      => implode(',', $numbers),
                'from'    => $this->from,
                'message' => $this->msg,
                'coding'  => $encoding,
            ))
        );

        // Time to wait
        sleep($this->waitingSeconds);

        $response = wp_remote_post($this->wsdl_link . "/message", $args);

        return $this->handleResponse($response, $numbers);
    }

    /**
     * Check response
     *
     * @param $response
     *
     * @param $numbers
     *
     * @return array|\WP_Error
     */
    private function handleResponse($response, $numbers)
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $response = json_decode($response['body']);

        /**
         * Backward compatibility
         */
        if (is_array($response)) {
            foreach ($response as $item) {
                if (!$item->Success or $item->Success == '') {
                    return new \WP_Error('send-sms', $item->Description);
                }
            }
        } else if (!$response->Success or $response->Success == '') {
            return new \WP_Error('send-sms', $response->Description);
        }

        // Log the result
        $this->log($this->from, $this->msg, $numbers, $response);

        return $response;
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username or !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'username'     => $this->username,
                'password'     => $this->password,
            )
        );

        $response = wp_remote_get($this->wsdl_link . "/balance", $args);

        // Check response
        if (is_wp_error($response)) {
            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $result = json_decode($response['body'], true);

        if ($result['Success'] == 1) {
            if ($result['Balance'] == 0) {
                return new \WP_Error('account-credit', sprintf('%s: %s', $result['Description'], $result['Balance']));
            }

            return $result['Balance'];
        }

        return new \WP_Error('account-credit', $result['Description']);
    }
}