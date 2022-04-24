<?php

namespace WP_SMS\Gateway;

class espay extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.espay.id/";
    public $tariff = "https://espay.id/";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->gatewayFields = [
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'API Sender ID',
                'desc' => 'Enter Sender ID of gateway',
            ],
            'signaturekey'  => [
                'id'   => 'gateway_signaturekey',
                'name' => 'Signature key',
                'desc' => 'Enter Signature Key of gateway'
            ]
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

        $country_code = isset($this->options['mobile_county_code']) ? $this->options['mobile_county_code'] : '';

        foreach ($this->to as $number) {
            $to           = $this->clean_number($number, $country_code);
            $rq_uuid      = $this->get_uuid();
            $sender_id    = $this->from;
            $message_type = 'SMS';
            $phone_number = $to;
            $message      = $this->msg;
            $signaturekey = $this->signaturekey;
            $signature    = hash('sha256', strtoupper(sprintf('#%s#%s#%s#%s#', $sender_id, $rq_uuid, $message_type, $phone_number)) . $signaturekey . '#');
            $response = wp_remote_post($this->wsdl_link . 'btext/send/outgoing', [
                'body'    => [
                    'rq_uuid'      => $rq_uuid,
                    'sender_id'    => $sender_id,
                    'message_type' => $message_type,
                    'phone_number' => $phone_number,
                    'message'      => $message,
                    'signature'    => $signature,
                ]
            ]);
        }

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $result = json_decode($response['body'], true);

            if ($result['error_code'] == '0000') {

                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result);

                /**
                 * Run hook after send sms.
                 *
                 * @param string $result result output.
                 * @since 2.4
                 *
                 */
                do_action('wp_sms_send', $result);

                return $result;
            } else {

                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result['error_desc'], 'error');

                return new \WP_Error('send-sms', $result['error_desc']);
            }
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        return true;
    }

    private function clean_number($number, $country_code)
    {
        //Clean Country Code from + or 00
        $country_code = str_replace('+', '', $country_code);

        if (substr($country_code, 0, 2) == "00") {
            $country_code = substr($country_code, 2, strlen($country_code));
        }

        //Remove +
        $number = str_replace('+', '', $number);

        if (substr($number, 0, strlen($country_code) * 2) == $country_code . $country_code) {
            $number = substr($number, strlen($country_code) * 2);
        } else {
            $number = substr($number, strlen($country_code));
        }

        //Remove 00 in the begining
        if (substr($number, 0, 2) == "00") {
            $number = substr($number, 2, strlen($number));
        }

        //Remove 00 in the begining
        if (substr($number, 0, 1) == "0") {
            $number = substr($number, 1, strlen($number));
        }

        $number = $country_code . $number;

        return $number;
    }

    private function get_uuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    }
}
