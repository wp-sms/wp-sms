<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

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
            'id'           => 'gateway_sender_id',
            'name'         => 'Sender Number',
            'place_holder' => 'e.g., +1 555 123 4567',
            'desc'         => 'This is the number or sender ID displayed on recipients’ devices.
It might be a phone number (e.g., +1 555 123 4567) or an alphanumeric ID if supported by your gateway.',
        ]
    ];

    public function __construct()
    {
    }

    public function SendSMS()
    {
        $errorMessage = esc_html__('The SMS gateway in the plugin is not configured yet.', 'wp-sms');

        return new \WP_Error('send-sms', $errorMessage);
    }

    public function GetCredit()
    {
        return null;
    }
}