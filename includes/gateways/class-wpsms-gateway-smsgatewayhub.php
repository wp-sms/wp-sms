<?php

namespace WP_SMS\Gateway;

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

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->has_key = true;
        $this->help           = "Please enter your API Key and DLT Template ID";
        $this->validateNumber = "91989xxxxxxx,91999xxxxxxx";
        $this->gatewayFields = [
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'Enter API key of gateway.',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'Approved Sender ID',
                'desc' => 'Enter sender ID of gateway.',
            ],
            'dlt_template_id'  => [
                'id'   => 'dlt_template_id',
                'name' => 'Registered DLT Template ID',
                'desc' => 'Enter your Registered DLT Template ID.',
            ],
            'entity_id'  => [
                'id'   => 'entity_id',
                'name' => 'Registered Entity ID',
                'desc' => 'Enter your Registered Entity ID. This field is optional.',
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

            // Get the Credit.
            $credit = $this->GetCredit();

            // Check gateway credit
            if (is_wp_error($credit)) {
                throw new \Exception($credit->get_error_message());
            }

            $dcs = isset($this->options['send_unicode']) ? '0' : '8';
            $flash_sms = $this->isflash ? '1' : '0';

            $params = [
                'APIKey' => $this->has_key,
                'senderid' => $this->from,
                'channel' => '2',
                'DCS' => $dcs,
                'flashsms' => $flash_sms,
                'number' => implode(',',$this->to),
                'text'=> $this->msg,
                'route' => '1',
                'EntityId' => $this->entity_id,
                'dlttemplateid' => $this->dlt_template_id
            ];

            $response = $this->request('POST', "{$this->wsdl_link}/SendSMS", $params, []);

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

            if ($response->ErrorCode !== 0) {
                throw new \Exception($response->ErrorMessage);
            }

            return $response->Balance;

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            return new \WP_Error('account-credit', $error_message);
        }
    }
}