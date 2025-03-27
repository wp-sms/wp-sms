<?php

namespace WP_SMS\Gateway;

class smsozone extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://smsozone.com/api/mt/";
    public $tariff = "http://ozonecmc.com/";
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
        $this->validateNumber = "e.g. 91989xxxxxxx";
        $this->help           = "Please enter your API Key and DLT Template ID";
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
            'dlt_template_id'  => [
                'id'   => 'dlt_template_id',
                'name' => __('Registered DLT Template ID', 'wp-sms'),
                'desc' => __('Enter your Registered DLT Template ID.', 'wp-sms'),
            ],
            'entity_id'  => [
                'id'   => 'entity_id',
                'name' => __('DLT Entity ID', 'wp-sms'),
                'desc' => __('Enter your Registered Entity ID. This field is optional.', 'wp-sms'),
            ],
            'channel' => [
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
            $dcs = isset($this->options['send_unicode']) ? '0' : '8';
            $flash_sms = $this->isflash ? '1' : '0';

            $params = [
                'APIKey' => $this->has_key,
                'senderid' => $this->from,
                'channel' => $this->channel,
                'DCS' => $dcs,
                'flashsms' => $flash_sms,
                'number' => implode(',',$this->to),
                'text'=> $this->msg,
                'route' => '1',
                'PEID' => $this->entity_id,
                'DLTTemplateId' => $this->dlt_template_id
            ];

            $response = $this->request('GET', "{$this->wsdl_link}/SendSMS", $params);

            if (isset($response->ErrorCode) && $response->ErrorCode !== '000') {
                throw new \Exception($response->ErrorMessage);
            }

            //log the result
            $this->log ($this->from, $this->msg, $this->to, $response);

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