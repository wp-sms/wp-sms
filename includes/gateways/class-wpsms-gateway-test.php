<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;


class test extends \WP_SMS\Gateway
{
    private $wsdl_link = '';
    public $tariff = '';
    public $unitrial = true;
    public $unit;
    public $flash = "false";
    public $isflash = false;
    public $options;
    public $async_support = true;

    public function __construct()
    {
        $this->options = \WP_SMS\Option::getOptions();

        parent::__construct();
        $this->help           = "";
        $this->validateNumber = "09xxxxxxxx";
        $this->has_key        = true;
        $this->bulk_send      = true;
        $this->gatewayFields  = [
            'from' => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'Enter your sender ID',
            ],
        ];
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

        foreach ($this->to as $number) {
            $this->handleRequest('GET', 'http://localhost/endpoint', [
                'from'    => $this->from,
                'to'      => $number,
                'message' => $this->msg,
            ], [], $this->options['async_send_status'], $number);
        }
    }

    public function GetCredit()
    {
        return '143 USD';
    }
}