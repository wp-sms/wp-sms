<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class oxemis extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.oxisms.com";
    public $tariff = "https://www.oxemis.com/en/sms";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->help           = 'For passing the CampaignName in your message, please add |CampaignName after your messages, example: Hello|CampaignName';
        $this->validateNumber = 'Phone number to contact. You should use the international MSISDN format (33601020304) but you can also use the national number if this number is a French Number (0601020304). The number is "cleaned" from special chars (like "." ou "+").';
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

            $messageTemplate = $this->getTemplateIdAndMessageBody();
            $options         = [];

            if (isset($messageTemplate['template_id'])) {
                $options['CampaignName'] = $messageTemplate['template_id'];
                $this->msg               = $messageTemplate['message'];
            }

            $params = array(
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode([
                    'Options'    => $options,
                    'Message'    => [
                        'Sender' => $this->from,
                        'Text'   => $this->msg,
                    ],
                    'Recipients' => array_map(function ($number) {
                        return [
                            'PhoneNumber' => $number,
                        ];
                    }, $this->to),
                ]),
            );

            $response = $this->request('POST', "{$this->wsdl_link}/send", [], $params, false);

            if (isset($response->Code)) {
                throw new Exception($response->Message);
            }

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
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

            // Check API key and API Password
            if (!$this->username || !$this->password) {
                return new WP_Error('account-credit', esc_html__('The API Key and API Password are required.', 'wp-sms'));
            }

            $params = array(
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                    'Content-Type'  => 'application/json',
                ]
            );

            $response = $this->request('GET', "{$this->wsdl_link}/user", [], $params, false);

            if (isset($response->Code)) {
                throw new Exception($response->Message);
            }

            return $response->Credits;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }
}