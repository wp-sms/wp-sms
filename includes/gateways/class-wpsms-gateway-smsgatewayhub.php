<?php

namespace WP_SMS\Gateway;

use WP_SMS\Helper;

class smsgatewayhub extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.smsgatewayhub.com/api/mt";
    public $tariff = "https://www.smsgatewayhub.com";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    public $entity_id = '';
    public $dlt_template_id;
    public $channel = 'Trans';

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->has_key        = true;
        $this->help           = "Please enter your API Key and DLT Template ID. For <b>other Template IDs</b>, you can specify it <b>message|template_id</b> when sending a message. If nothing is defined when sending the message, the Template ID specified in the settings will be used.";
        $this->validateNumber = "91989xxxxxxx,91999xxxxxxx";
        $this->gatewayFields  = [
            'has_key'         => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'Enter API key of gateway.',
            ],
            'from'            => [
                'id'   => 'gateway_sender_id',
                'name' => 'Approved Sender ID',
                'desc' => 'Enter sender ID of gateway.',
            ],
            'dlt_template_id' => [
                'id'   => 'dlt_template_id',
                'name' => 'Registered DLT Template ID',
                'desc' => 'Enter your Registered DLT Template ID.',
            ],
            'entity_id'       => [
                'id'   => 'entity_id',
                'name' => 'Registered Entity ID',
                'desc' => 'Enter your Registered Entity ID. This field is optional.',
            ],
            'channel'         => [
                'id'      => 'channel',
                'name'    => __('SMS Channel', 'wp-sms'),
                'desc'    => __('Please select SMS channel.', 'wp-sms'),
                'type'    => 'select',
                'options' => [
                    'Trans' => __('Transactional', 'wp-sms'),
                    'Promo' => __('Promotional', 'wp-sms'),
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
            $dcs       = isset($this->options['send_unicode']) ? '0' : '8';
            $flash_sms = $this->isflash ? '1' : '0';

            // Remove leading + and 00 sign from the beginning of numbers
            $this->to = Helper::removeNumbersPrefix(['+', '00'], $this->to);

            $messageText       = $this->msg;
            $messageTemplateID = $this->dlt_template_id;

            $message = $this->getTemplateIdAndMessageBody();

            if (isset($message['message'], $message['template_id'])) {
                $messageText       = $message['message'];
                $messageTemplateID = $message['template_id'];
            }

            $params = [
                'APIKey'        => $this->has_key,
                'senderid'      => $this->from,
                'channel'       => $this->channel,
                'DCS'           => $dcs,
                'flashsms'      => $flash_sms,
                'number'        => implode(',', $this->to),
                'text'          => $messageText,
                'route'         => '1',
                'PEID'          => $this->entity_id,
                'DLTTemplateId' => $messageTemplateID
            ];

            $response = $this->request('GET', "{$this->wsdl_link}/SendSMS", $params);

            if (isset($response->ErrorCode) && $response->ErrorCode !== '000') {
                throw new \Exception($response->ErrorMessage);
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

        } catch (\Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        try {
            // Check gateway API
            if (!$this->has_key) {
                throw new \Exception('The API Key for this gateway is not set');
            }

            $params = [
                'APIKey' => $this->has_key
            ];

            $response = $this->request('GET', "{$this->wsdl_link}/GetBalance", $params, []);

            if ($response->ErrorCode != 0) {
                throw new \Exception($response->ErrorMessage);
            }

            return $response->Balance;

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            return new \WP_Error('account-credit', $error_message);
        }
    }
}
