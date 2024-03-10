<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class fast2sms extends Gateway
{
    private $wsdl_link = "https://www.fast2sms.com/dev";
    public $tariff = "https://www.fast2sms.com";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    public $entity_id = '';
    public $dlt_template_id;
    public $route = 'dlt_manual';
    public $message_id;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->validateNumber = __('Enter number without country code.', 'wp-sms');
        $this->help           = __('Please enter your API Key and DLT Template ID. For DLT messages, send variables in the message body separated via "|" symbol. e.g. Rahul|8888888888|6695', 'wp-sms');
        $this->has_key = true;
        $this->gatewayFields = [
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => __('API Key', 'wp-sms'),
                'desc' => __('Enter API key of gateway.', 'wp-sms'),
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => __('Approved Sender ID', 'wp-sms'),
                'desc' => __('Enter sender ID of gateway.', 'wp-sms'),
            ],
            'message_id'  => [
                'id'   => 'message_id',
                'name' => __('Approved Message ID', 'wp-sms'),
                'desc' => __('Enter your message ID.', 'wp-sms'),
            ],
            'dlt_template_id'  => [
                'id'   => 'dlt_template_id',
                'name' => __('Registered DLT Template ID', 'wp-sms'),
                'desc' => __('Enter your Registered DLT Template ID.', 'wp-sms'),
            ],
            'entity_id'  => [
                'id'   => 'entity_id',
                'name' => __('Entity ID', 'wp-sms'),
                'desc' => __('Enter your Registered Entity ID.', 'wp-sms'),
            ],
            'route' => [
                'id'      => 'route',
                'name'    => __('Route', 'wp-sms'),
                'desc'    => __('Please select SMS route.', 'wp-sms'),
                'type'    => 'select',
                'options' => [
                    "dlt"           => __('DLT SMS', 'wp-sms'),
                    "dlt_manual"    => __('DLT Manual SMS', 'wp-sms'),
                    "q"             => __('Quick SMS', 'wp-sms')
                ]
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

        try {
            $flash_sms = $this->isflash ? '1' : '0';

            $params = [
                'authorization' => $this->has_key,
                'sender_id'     => $this->from,
                'route'         => $this->route,
                'flash'         => $flash_sms,
                'numbers'       => implode(',',$this->to),
                'message'       => $this->msg,
                'entity_id'     => $this->entity_id,
                'template_id'   => $this->dlt_template_id
            ];

            if ($this->route === 'dlt') {
                $params['message']          = $this->message_id;
                $params['variables_values'] = $this->msg;
            }

            $response = $this->request('GET', "{$this->wsdl_link}/bulkV2", $params, [], false);

            if ($response->return != true) {
                throw new Exception($response->message);
            }

            //log the result
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

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        try {
            if (!$this->has_key) {
                throw new Exception('The API Key for this gateway is not set.');
            }

            $params = [
                'authorization' => $this->has_key
            ];

            $response = $this->request('GET', "{$this->wsdl_link}/wallet", $params, [], false);

            if ($response->return != true) {
                throw new Exception($response->message);
            }

            return $response->wallet;
        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }
}