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
    public $gatewayFields = [
        'from' => [
            'id'   => 'gateway_sender_id',
            'name' => 'Sender number',
            'desc' => 'Sender number or sender ID',
        ]
    ];

    public function __construct()
    {
    }

    public function SendSMS()
    {
        $errorMessage = __('The SMS gateway in the plugin is not configured yet.', 'wp-sms');

        return new \WP_Error('send-sms', $errorMessage);
    }

    public function GetCredit()
    {
        return null;
    }
}