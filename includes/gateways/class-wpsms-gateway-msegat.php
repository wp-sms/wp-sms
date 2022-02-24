<?php

namespace WP_SMS\Gateway;

class msegat extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.msegat.com/gw/";
    public $tariff = "https://www.msegat.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->gatewayFields = [
            'username' => [
                'id'   => 'gateway_username',
                'name' => 'API username',
                'desc' => 'Enter API username of gateway',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender number',
                'desc' => 'Sender number or sender ID',
            ],
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'API key',
                'desc' => 'Enter API key of gateway'
            ]
        ];
        $this->has_key        = true;
        $this->help           = "Use username as your username and use the API/Key as your API.";
        $this->validateNumber = "The phone number(s) the message should be sent to (must be in international format, like 966xxxxxxxxx). ";
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

        $encoding = 'windows-1256';
        if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
            $encoding = 'UNICODE';
        }

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body'    => json_encode(array(
                'userName'     => $this->username,
                'apiKey'       => $this->has_key,
                'msgEncoding ' => $encoding,
                'userSender'   => $this->from,
                'numbers'      => implode(',', $this->to),
                'msg'          => $this->msg
            ))
        );

        $response = wp_remote_post($this->wsdl_link . "sendsms.php", $args);

        // Check response
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');
            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $responseObject = json_decode($response['body']);

        if ($responseObject->code == 1 and wp_remote_retrieve_response_code($response) == 200) {

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $responseObject);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             *
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $responseObject);

            return $responseObject;

        } else {

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $responseObject->message, 'error');

            return new \WP_Error('send-sms', $responseObject->message);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username) {
            return new \WP_Error('account-credit', __('The Username for this gateway is not set', 'wp-sms'));
        }

        // Check api key
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('The API Key for this gateway is not set', 'wp-sms'));
        }

        $args = array(
            'body' => array(
                'userName'     => $this->username,
                'apiKey'       => $this->has_key,
                'msgEncoding ' => 'UTF8'
            )
        );

        $response = wp_remote_post($this->wsdl_link . "Credits.php", $args);

        // Check response
        if (is_wp_error($response)) {
            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $errorMessage = $this->getErrorMessage($response['body']);

        if (false == $errorMessage and wp_remote_retrieve_response_code($response) == 200) {
            return $response['body'];
        } else {
            return new \WP_Error('account-credit', $errorMessage);
        }
    }

    /**
     * @param $result
     *
     * @return string|\WP_Error
     */
    private function getErrorMessage($result)
    {

        switch ($result) {
            case 'M0001':
                $error = ' Variables missing.';
                break;
            case '1020':
            case 'M0002':
                $error = 'Invalid login info.';
                break;
            case 'M0022':
                $error = 'Exceed number of senders allowed.';
                break;
            case 'M0023':
                $error = 'Sender Name is active or under activation or refused.';
                break;
            case 'M0024':
                $error = 'Sender Name should be in English or number.';
                break;
            case 'M0025':
                $error = 'Invalid Sender Name Length.';
                break;
            case 'M0026':
                $error = 'Sender Name is already activated or not found.';
                break;
            case 'M0027':
                $error = 'Activation Code is not Correct.';
                break;
            case 'M0029':
                $error = 'Invalid Sender Name - Sender Name should contain only letters, numbers and the maximum length should be 11 characters.';
                break;
            case 'M0030':
                $error = 'Sender Name should ended with AD.';
                break;
            case 'M0031':
                $error = 'Maximum allowed size of uploaded file is 5 MB.';
                break;
            case 'M0032':
                $error = 'Only pdf,png,jpg and jpeg files are allowed!.';
                break;
            case 'M0033':
                $error = 'Sender Type should be normal or whitelist only.';
                break;
            case 'M0034':
                $error = 'Please Use POST Method.';
                break;
            case 'M0036':
                $error = 'There is no any sender.';
                break;
            case '1010':
                $error = 'Variables missing.';
                break;
            case '1050':
                $error = 'MSG body is empty.';
                break;
            case '1060':
                $error = 'Balance is not enough.';
                break;
            case '1061':
                $error = 'MSG duplicated.';
                break;
            case '1064':
                $error = 'Free OTP , Invalid MSG content you should use "Pin Code is: xxxx" or "Verification Code: xxxx" or "رمز التحقق: 1234" , or upgrade your account and activate your sender to send any content.';
                break;
            case '1110':
                $error = 'Sender name is missing or incorrect.';
                break;
            case '1120':
                $error = 'Mobile numbers is not correct.';
                break;
            case '1140':
                $error = 'MSG length is too long.';
                break;
            case 'M0000':
            default:
                $error = false;
                break;
        }

        return $error;
    }
}