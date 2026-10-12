<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

require_once __DIR__ . '/class-wpsms-gateway-globalvoice.php';

/**
 * SMSense (smsense.com) by Global Voice. It uses the same REST API as Global Voice,
 * served from the SMSense retail host.
 */
class smsense extends globalvoice
{
    protected $wsdl_link = "https://retail.smsense.com/rest/";
    public $tariff = "https://smsense.com/";

    public function __construct()
    {
        parent::__construct();
        $this->help = 'Enter the API Token from your SMSense account.';
    }
}
