<?php

namespace WP_SMS\Gateway;

class signalads extends \WP_SMS\Gateway
{
    const BASE_URL = "http://panel.signalads.com/rest/api/v1/";

    public $tariff = "";
    public $unitrial = false;
    public $unit;
    public $flash = false;
    public $isflash = false;

    private function get_path($route)
    {
        return self::BASE_URL . $route;
    }

    private function getHeaders()
    {
        return array(
            'Authorization' => "Bearer $this->has_key"
        );
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

        $path     = $this->get_path("message/send.json");
        $response = wp_remote_get($path, array(
            'headers' => $this->getHeaders(),
            'body'    => array(
                'numbers' => $this->to,
                'from'    => $this->from,
                'message' => $this->msg
            )
        ));

        if (is_array($response) && !is_wp_error($response)) {
            try {
                $json = json_decode($response['body']);
                if ($json && $json->success) {
                    // Log the result
                    $this->log($this->from, $this->msg, $this->to, $json);
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
        $credit = 0;
        $path         = $this->get_path("user/credit.json");
        $response     = wp_remote_get($path, array(
            'headers' => $this->getHeaders()
        ));
        if (is_array($response) && !is_wp_error($response)) {
            try {
                $json = json_decode($response['body']);
                if ($json) {
                    $credit = (int)$json->data->credit;
                }
            } catch (\Exception $ex) {
                return new \WP_Error('broke', __("credit_api => invalid data"));
            }
        }

        if($credit < 5){
            return new \WP_Error('broke', __("اعتبار برای ارسال پیامک کافی نمی‌باشد"));
        }

        return $credit;
    }
}