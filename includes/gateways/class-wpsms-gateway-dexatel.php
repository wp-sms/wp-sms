<?php

namespace WP_SMS\Gateway;

class dexatel extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://sms.dexatel.com:8001/api";
    public $tariff = "https://dexatel.com";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = 'Must be sent in international E.164 format (up to 15 digits allowed)';
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
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');
            return $credit;
        }

        $request = add_query_arg([
            'username' => $this->username,
            'password' => $this->password,
            'dnis'     => implode(',', $this->to),
            'ani'      => $this->from,
            'message'  => urlencode($this->msg),
            'command'  => 'submit',
        ], $this->wsdl_link);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL            => $request,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
        ));

        $httpCode     = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $response     = curl_exec($curl);
        $errorMessage = curl_error($curl);

        curl_close($curl);

        if ($httpCode != 200) {
            $this->log($this->from, $this->msg, $this->to, $errorMessage, 'error');
            return new \WP_Error('send-sms', $errorMessage);
        }

        $responseBody = json_decode($response);

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

        return $responseBody;
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('Username/Password is required.', 'wp-sms'));
        }

        return 1;
    }
}