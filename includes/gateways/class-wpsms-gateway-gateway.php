<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class gateway extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://rest.gateway.sa/api";
    public $tariff = "http://sms.gateway.sa";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->has_key        = true;
        $this->validateNumber = "+966556xxxxxx";
        $this->help           = 'For passing the Template ID in your message, please add |templateid after your messages, example: Hello|909';
        $this->gatewayFields  = [
            'has_key'  => [
                'id'   => 'gateway_key',
                'name' => 'API ID',
                'desc' => 'Please enter your API ID.',
            ],
            'password' => [
                'id'   => 'gateway_password',
                'name' => 'API Password',
                'desc' => 'Enter your API password.',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender ID',
                'desc' => 'Please enter your sender ID.',
            ],
        ];
    }

    public function SendSMS()
    {

        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         *
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         *
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         *
         * @since 3.4
         *
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {

            $encoding = 'T';

            if ($this->options['send_unicode']) {
                $encoding = 'U';
            }

            if ($this->isflash) {
                $encoding = 'U';
            }

            if ($this->options['send_unicode'] && $this->isflash) {
                $encoding = 'UFS';
            }

            $arguments = [
                'api_id'       => $this->has_key,
                'api_password' => $this->password,
                'sms_type'     => 'T',
                'encoding'     => $encoding,
                'sender_id'    => $this->from,
                'phonenumber'  => implode(',', $this->to),
                'textmessage'  => $this->msg
            ];

            $template = $this->getTemplateIdAndMessageBody();

            if (isset($template['template_id'])) {
                $arguments['template_id'] = $template['template_id'];
                $this->msg                = $template['message'];
            }

            $response = $this->request('GET', "{$this->wsdl_link}/SendSMSMulti", $arguments, []);

            if (isset($response->status) && $response->status == 'F') {
                throw new Exception($response->remarks);
            }

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
             *
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

            $response = $this->request('GET', "{$this->wsdl_link}/CheckBalance", [
                'api_id'       => $this->has_key,
                'api_password' => $this->password
            ], []);

            if (!isset($response->BalanceAmount)) {
                throw new Exception($response);
            }

            return $response->BalanceAmount;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

}