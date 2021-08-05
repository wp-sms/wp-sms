<?php

namespace WP_SMS\Gateway;

class bulutfon extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.bulutfon.com/";
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

        $data = [
            'title'     => $this->from,
            'email'     => $this->username,
            'password'  => $this->password,
            'receivers' => implode(',', $this->to),
            'content'   => $this->msg,
        ];

        $response = wp_remote_post($this->wsdl_link . 'messages', ['body' => $data]);

        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        } else {
            $result = wp_remote_retrieve_body($response);
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
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . 'me' . '?email=' . $this->username . '&password=' . $this->password);

        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        } else {
            $result     = wp_remote_retrieve_body($response);
            $result_arr = json_decode($result);

            if (!empty($result_arr->credit->sms_credit)) {
                return $result_arr->credit->sms_credit;
            } else {
                return new \WP_Error('account-credit', $result);
            }
        }
    }
}
