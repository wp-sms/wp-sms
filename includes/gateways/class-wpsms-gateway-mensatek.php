<?php

namespace WP_SMS\Gateway;

class mensatek extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.mensatek.com/v5";
    public $tariff = "https://www.mensatek.com/precios-sms.php";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "";
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

        $to       = implode(";",$this->to);
        $sms_text = iconv('utf-8', 'ISO-8859-1//TRANSLIT', $this->msg);

        $response = wp_remote_get($this->wsdl_link . "/enviar.php?Correo=" . $this->username . "&Passwd=" . $this->password . "&Destinatarios=" . $to . "&Remitente=" . $this->from . "&Mensaje=" . $sms_text . "&Report=0&Resp=JSON");

        // Check response error
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $result = json_decode($response['body']);

        if ($result->Res != '-1') {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);

            /**
             * Run hook after send sms.
             *
             * @since 2.4
             */
            do_action('wp_sms_send', $response['body']);

            return $result;
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result->Msgid, 'error');

            return new \WP_Error('send-sms', $result->Msgid);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . "/creditos.php?Correo=" . $this->username . "&Passwd=" . $this->password . "&Resp=JSON");

        if (!is_wp_error($response)) {
            $data = json_decode($response['body']);

            return $data->Cred;
        } else {
            return new \WP_Error('account-credit', $response->get_error_message());
        }
    }
}