<?php

namespace WP_SMS\Gateway;


use Exception;
use WP_Error;

class bulkgate extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://portal.bulkgate.com/api/1.0";
    public $tariff = "https://www.bulkgate.com/";
    public $flash = "false";
    public $isflash = false;
    public $unitrial = true;
    public $unit;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send     = true;
        $this->has_key       = true;
        $this->help          = '<a href="https://jawalbsms.ws">Take your own api token</a> ';
        $this->gatewayFields = [
            'from'    => [
                'id'   => 'gateway_sender_name',
                'name' => 'Application id',
                'desc' => 'Enter application id of gateway',
            ],
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => 'Application token',
                'desc' => 'Enter API key of gateway'
            ]
        ];
    }

    public function SendSMS()
    {
        /**
         * Modify sender id
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {

            $arguments = array(
                'application_id'    => $this->from,
                'application_token' => $this->has_key,
                'number'            => $this->to,
                'text'              => $this->msg,
            );

            //send promotioanl sms
            if (count($this->to) > 1) {
                $response = $this->request('GET', "{$this->wsdl_link}/simple/promotional", $arguments, [], false);
                exit;
            }else {
                $response = $this->request('GET', "{$this->wsdl_link}/simple/transactional", $arguments, [], false);
            }

            if ($response->code == '400') {
                throw new Exception($response->type);
            }

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $responseLog);

            /*
             * Run hook after send sms.
             *
             */
            do_action('wp_sms_send', $response);

            return $response;
        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');
            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        try {
            // Check Api key
            if (!$this->from or !$this->has_key) {
                throw new Exception(__('Application id and Application token for this gateway are require.', 'wp-sms'));
            }

            $arguments = [
                'application_id'    => $this->from,
                'application_token' => $this->has_key,
            ];

            $response = $this->request('GET', "{$this->wsdl_link}/simple/info", $arguments, [], false);

            if (!isset($response->data)) {
                if ($response->code == '401') {
                    throw new Exception($response->error);
                }
            }

            return $response->data->credit;

        } catch (\Throwable $e) {
            return new \WP_Error('get-credit', $e->getMessage());
        }
    }
}

