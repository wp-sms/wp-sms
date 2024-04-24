<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;
use WP_SMS\Helper;

class fast2sms extends Gateway
{
    private $wsdl_link = "https://www.fast2sms.com/dev";
    public $tariff = "https://www.fast2sms.com";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    public $entity_id = '';
    public $route = 'dlt_manual';

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->validateNumber = esc_html__('Enter number without country code.', 'wp-sms');
        $this->help           = __('For <b>DLT</b> messages, send variables and message id in this format: <b>var1|var2|message_id</b>. <br> For <b>DLT Manual</b> messages, send message and template id in the same way. e.g. <b>message|template_id</b>', 'wp-sms');
        $this->has_key = true;
        $this->gatewayFields = [
            'route' => [
                'id'      => 'route',
                'name'    => esc_html__('Route', 'wp-sms'),
                'desc'    => esc_html__('Please select SMS route.', 'wp-sms'),
                'type'    => 'select',
                'options' => [
                    "dlt"           => esc_html__('DLT SMS', 'wp-sms'),
                    "dlt_manual"    => esc_html__('DLT Manual SMS', 'wp-sms'),
                    "q"             => esc_html__('Quick SMS', 'wp-sms')
                ]
            ],
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => esc_html__('API Key', 'wp-sms'),
                'desc' => esc_html__('Enter API key of gateway.', 'wp-sms'),
            ],
            'from'     => [
                'id'        => 'gateway_sender_id',
                'name'      => esc_html__('Approved Sender ID', 'wp-sms'),
                'desc'      => esc_html__('Enter sender ID of gateway.', 'wp-sms'),
                'className' => 'js-wpsms-show_if_route_equal_dlt js-wpsms-show_if_route_equal_dlt_manual'
            ],
            'entity_id'  => [
                'id'        => 'entity_id',
                'name'      => esc_html__('Entity ID', 'wp-sms'),
                'desc'      => esc_html__('Enter your Registered Entity ID.', 'wp-sms'),
                'className' => 'js-wpsms-show_if_route_equal_dlt_manual'
            ]
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

            // Remove India country code from the beginning of numbers
            $this->to = Helper::removeNumbersPrefix(['91', '+91', '0091'], $this->to);

            $params = [
                'authorization' => $this->has_key,
                'sender_id'     => $this->from,
                'route'         => $this->route,
                'flash'         => $flash_sms,
                'numbers'       => implode(',',$this->to),
                'message'       => $this->msg,
                'entity_id'     => $this->entity_id,
            ];

            if ($this->route === 'dlt') {
                $message        = explode('|', $params['message']);
                $messageId      = array_pop($message);
                $variableValues = implode('|', $message);

                $params['message']          = $messageId;
                $params['variables_values'] = $variableValues;
            } else if ($this->route === 'dlt_manual') {
                $message = $this->getTemplateIdAndMessageBody();

                if (isset($message['message'], $message['template_id'])) {
                    $params['message']      = $message['message'];
                    $params['template_id']  = $message['template_id'];
                }
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