<?php

namespace WP_SMS\Gateway;

class kavenegar extends \WP_SMS\Gateway
{
    const APIPATH = "http://api.kavenegar.com/v1/%s/%s/%s.json/";

    private $wsdl_link = "";
    public $tariff = "";
    public $unitrial = false;
    public $unit;
    public $flash = false;
    public $isflash = false;

    private function get_path($method, $base = 'sms')
    {
        return sprintf(self::APIPATH, trim($this->has_key), $base, $method);
    }

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "+xxxxxxxxxxxxx";
        $this->has_key        = true;
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

        $to       = implode(",",$this->to);
        $msg      = urlencode($this->msg);
        $path     = $this->get_path("send");
        $response = wp_remote_get($path, array(
            'body' => array(
                'receptor' => $to,
                'sender'   => $this->from,
                'message'  => $msg
            )
        ));
        if (is_array($response) && !is_wp_error($response)) {
            try {
                $json = json_decode($response['body']);
                if ($json && $json->return->status == 200) {
                    // Log the result
                    $this->log($this->from, $this->msg, $this->to, $response);
                    do_action('wp_sms_send', $response);

                    return $response;
                }
            } catch (\Exception $ex) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response, 'error');

                return new \WP_Error('send-sms', $response);
            }
        }
        // Log the result
        $this->log($this->from, $this->msg, $this->to, $response, 'error');

        return new \WP_Error('send-sms', $response);
    }

    public function GetCredit()
    {
        $remaincredit = 0;
        $path         = $this->get_path("info", "account");
        $response     = wp_remote_get($path);
        if (is_array($response) && !is_wp_error($response)) {
            try {
                $json = json_decode($response['body']);
                if ($json) {
                    $remaincredit = $json->entries->remaincredit;
                }
            } catch (\Exception $ex) {
                $remaincredit = 0;
            }
        }

        return $remaincredit;
    }
}