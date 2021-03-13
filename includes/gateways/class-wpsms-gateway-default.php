<?php

namespace WP_SMS\Gateway;

use WP_SMS\Gateway;

class Default_Gateway extends Gateway
{
    private $wsdl_link = '';
    public $tariff = '';
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    public $bulk_send = false;

    public function __construct()
    {
        $this->validateNumber = "1xxxxxxxxxx";
    }

    public function SendSMS()
    {
        // Check gateway credit
        if (is_wp_error($this->GetCredit())) {
            return new \WP_Error('account-credit', __('Your account does not credit for sending sms.', 'wp-sms'));
        }

        return new \WP_Error('send-sms', __('Does not set any gateway', 'wp-sms'));
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        return new \WP_Error('account-credit', 0);
    }
}