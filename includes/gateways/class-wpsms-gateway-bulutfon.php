<?php

namespace WP_SMS\Gateway;

class bulutfon extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.bulutfon.com/messages";
    public $tariff = "http://bulutfon.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "90xxxxxxxxxx";
    }

    public function SendSMS()
    {
        // Get the credit.
        $credit = $this->GetCredit();

        // Check gateway credit
        if (is_wp_error($credit)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return $credit;
        }

        $msg = urlencode($this->msg);

        $data = array(
            'title'     => $this->from,
            'email'     => $this->username,
            'password'  => $this->password,
            'receivers' => implode(',', $this->to),
            'content'   => $this->msg,
        );

        $data = http_build_query($data);
        $ch   = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->wsdl_link);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($ch);
        $json   = json_decode($result, true);

        if ($result) {
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

            return $json;
        }
        // Log the result
        $this->log($this->from, $this->msg, $this->to, $result, 'error');

        return new \WP_Error('send-sms', $result);
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('Username/Password does not set for this gateway', 'wp-sms'));
        }

        $result     = file_get_contents('https://api.bulutfon.com/me' . '?email=' . $this->username . '&password=' . $this->password);
        $result_arr = json_decode($result);

        return $result_arr->credit->sms_credit;
    }
}