<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class directsend extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://directsend.co.kr/index.php/api_v2/sms_change_word";
    public $tariff = "https://directsend.co.kr";
    public $unitrial = false;
    public $unit;
    public $flash = "false";
    public $isflash = false;
    public $kakao_plus_id;
    public $user_template_no;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->has_key        = true;
        $this->validateNumber = "Mobile numbers must start with '0' and should not include the country code.";
        $this->help           = "Note: Please ensure that the server's IP address (the host of this site) is added to the 'Allowed IP' list; otherwise, messages may not be successfully sent. The IP can be added by navigating to [마이페이지 > 회원정보 > 사용자 설정]. <br> Please note that the correct format for mobile numbers in this gateway does not include the country code and must start with a '0'.";
        $this->gatewayFields  = [
            'username'         => [
                'id'   => 'gateway_username',
                'name' => 'Username',
                'desc' => 'Directsend issued ID.',
            ],
            'has_key'          => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'Directsend issued API key.',
            ],
            'from'             => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender Number',
                'desc' => 'Enter the sender number.',
            ],
            'kakao_plus_id'    => [
                'id'   => 'kakao_plus_id',
                'name' => 'Kakao Plus ID',
                'desc' => 'Enter your Kakao plus ID.',
            ],
            'user_template_no' => [
                'id'   => 'user_template_no',
                'name' => 'User Template Number',
                'desc' => 'Enter the registered template number.',
            ],
        ];
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

        try {

            $numbers = array_map(function ($number) {
                return $this->clean_number($number);
            }, $this->to);

            $recipients = array_map(function ($recipient) {
                return array(
                    'mobile' => $recipient
                );
            }, $numbers);

            $from_explode = explode('|', $this->from);
            $apiUrl       = $this->wsdl_link;

            if (isset($from_explode[1]) && $from_explode[1] == 'kakao') {
                $apiUrl                                = 'https://directsend.co.kr/index.php/api_v2/kakao_notice';
                $arguments['body']['kakao_plus_id']    = $this->kakao_plus_id;
                $arguments['body']['user_template_no'] = $this->user_template_no;
            }

            $arguments['headers']['cache-control'] = 'no-cache';
            $arguments['headers']['content-type']  = 'application/json';
            $arguments['headers']['charset']       = 'utf-8';
            $arguments['body']['username']         = $this->username;
            $arguments['body']['key']              = $this->has_key;
            $arguments['body']['receiver']         = $recipients;
            $arguments['body']['message']          = $this->msg;
            $arguments['body']['sender']           = $from_explode[0];

            $arguments['body'] = wp_json_encode($arguments['body']);

            $response = $this->request('POST', "$apiUrl", [], $arguments);

            if (isset($response->status) && isset($response->message) && !in_array($response->status, [0, 1])) {
                throw new Exception($response->message);
            }

            //log the result
            $this->log($this->from, $this->msg, $numbers, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

            return $response;

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $numbers, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        try {
            // Check username and password
            if (!$this->username or !$this->has_key) {
                throw new Exception(esc_html__('The Username/API key for this gateway is not set.', 'wp-sms'));
            }
            return 1;

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            return new WP_Error('account-credit', $error_message);
        }
    }

    /**
     * remove the country code
     *
     * @param string $number
     *
     * @return string
     */
    public function clean_number($number)
    {
        $number = str_replace('+82', '', $number);

        if (substr($number, 0, 1) !== '0') {
            $number = '0' . $number;
        }

        return trim($number);
    }

}