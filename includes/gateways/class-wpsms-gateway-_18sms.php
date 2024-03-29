<?php

namespace WP_SMS\Gateway;

class _18sms extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://18sms.ir/webservice/rest";
    public $tariff = "http://18sms.ir/";
    public $unitrial = true;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09000000000";
        $this->has_key        = true;
    }

    /**
     * @return string|\WP_Error
     */
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
            $response = $this->request('GET', "{$this->wsdl_link}/sms_send", [
                'login_username'  => $this->username,
                'login_password'  => $this->password,
                'receiver_number' => implode(',', $this->to),
                'note_arr[]'      => $this->msg,
                'sender_number'   => $this->from,
            ], []);

            if (isset($response->status) and $response->status == 'ERR') {
                throw new \Exception($response->error_string);
            }

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

        } catch (\Exception $e) {

            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('send-sms', $e->getMessage());
        }
    }

    /**
     * @return int|\WP_Error
     */
    public function GetCredit()
    {
        try {
            if (!$this->username or !$this->password) {
                throw new \Exception(esc_html__('The API Key for this gateway is not set', 'wp-sms-pro'));
            }

            $response = $this->request('GET', "{$this->wsdl_link}/user_info", [
                'login_username' => $this->username,
                'login_password' => $this->password,
            ]);

            if (isset($response->status) and $response->status == 'ERR') {
                throw new \Exception($response->error_string);
            }

            if ($response->result == ':true') {
                return $response->list->cash;
            } else {
                throw new \Exception($response->error);
            }

        } catch (\Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }
    }
}