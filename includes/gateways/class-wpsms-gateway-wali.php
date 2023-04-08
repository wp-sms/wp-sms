<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class wali extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.wali.chat/v1";
    public $tariff = "https://wali.chat/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    public $token = '';
    public $device_id = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = false;
        $this->supportMedia   = true;
        $this->validateNumber = "Phone number with international prefix using the <a href='https://en.wikipedia.org/wiki/E.164'>E164 format</a> to send the message";
        $this->gatewayFields  = [
            'token'     => [
                'id'   => 'gateway_token',
                'name' => 'Token',
                'desc' => 'Please enter your Token.',
            ],
            'device_id' => [
                'id'   => 'gateway_device_id',
                'name' => 'Device ID',
                'desc' => 'Device ID to be used for message delivery. If not defined, the first created device will be used by default. You can use this to arbitrary send messages across multiple devices connected to your account. You can get the device ID from the Web Console > Devices',
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

            $postBody = [
                'phone'   => $this->to[0],
                'message' => $this->msg,
                'device'  => $this->device_id,
            ];

            if (count($this->media)) {
                $response = $this->uploadFile($this->media[0]);

                if ($response) {
                    $postBody['media'] = [
                        'file' => $response
                    ];
                }
            }

            $params = array(
                'headers' => [
                    'Token'        => $this->token,
                    'Content-Type' => 'application/json'
                ],
                'body'    => json_encode($postBody)
            );

            $response = $this->request('POST', $this->wsdl_link . '/messages', [], $params, false);

            if (isset($response->errors)) {
                throw new Exception(
                    'Error: ' . $response->errors[0]->path . ": " . $response->errors[0]->message
                );
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

    /**
     * @return string | WP_Error
     * @throws Exception
     */
    public function GetCredit()
    {
        try {
            if (!$this->token) {
                throw new Exception('Token is required.');
            }

            return true;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

    private function uploadFile($fileUrl)
    {
        try {
            $params = array(
                'headers' => [
                    'Token'        => $this->token,
                    'Content-Type' => 'application/json'
                ],
                'body'    => json_encode([
                    'url' => $fileUrl
                ])
            );

            $response = $this->request('POST', $this->wsdl_link . '/files', [], $params, false);

            if (isset($response->meta->file)) {
                return $response->meta->file;
            }

            if (is_array($response) and isset($response[0]->id)) {
                return $response[0]->id;
            }

        } catch (Exception $e) {
            // noting to do
        }
    }
}
