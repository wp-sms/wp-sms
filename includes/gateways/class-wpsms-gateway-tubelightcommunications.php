<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class tubelightcommunications extends Gateway
{
    private     $wsdl_link = "https://portal.tubelightcommunications.com";
//    public      $documentUrl = 'https://wp-sms-pro.com/resources/tubelight-communications-sms-gateway-configuration/';
    public      $unitrial = true;
    public      $unit;
    public      $flash = "disable";
    public      $isflash = false;
    public      $template_id = '';
    public      $validateNumber = "919811xxxxxx";
    public      $supportMedia = true;
    public      $route = 'sms';

    public function __construct()
    {
        parent::__construct();
        $this->help = __('SMS: please follow this format: <b>message|template_id</b><br>WhatsApp: please follow this format: <b>var1:var2:var3:var4|template_name</b>', 'wp-sms');
        $this->gatewayFields  = [
            'username'       => [
                'id'   => 'gateway_username',
                'name' => 'Username',
                'desc' => 'Username provided by Tubelight',
            ],
            'password'       => [
                'id'   => 'gateway_password',
                'name' => 'Password',
                'desc' => 'Password provided by Tubelight',
            ],
            'from'           => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender number',
                'desc' => 'Sender number or sender ID',
            ],
            'route'          => [
                'id'      => 'route',
                'name'    => esc_html__('Route', 'wp-sms'),
                'type'    => 'select',
                'options' => [
                    "sms"      => esc_html__('SMS', 'wp-sms'),
                    'whatsapp' => esc_html__('WhatsApp', 'wp-sms'),
                ],
                'desc'    => esc_html__('Please select the route.', 'wp-sms'),
            ],
        ];
    }

    /**
     * Send SMS
     */
    public function SendSMS(): WP_Error|string
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

        $token = $this->accessToken();

        if (is_wp_error($token)) {
            return $token;
        }

        $templateMessage = $this->getTemplateIdAndMessageBody();

        if (is_array($templateMessage)) {
            $template = $templateMessage['template_id'];
            $message = $templateMessage['message'];
            $messageVars = explode(':', $message);
            $messageVars = count($messageVars) === 1 ? [] : $messageVars;
        } else {
            $template = null;
            $message = $this->msg;
            $messageVars = [];
        }

        $params = [
            'headers'   => [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ],
        ];

        $responses = [];

        if ($this->route == 'sms') {
            foreach ($this->to as $number) {
                try {
                    $params['body'] = wp_json_encode([
                        'sender'        => $this->from,
                        'mobileNo'      => $number,
                        'messageType'   => 'TEXT',
                        'messages'      => $message,
                        'tempId'        => $template ?? '',
                    ]);

                    $response = $this->request('POST', $this->wsdl_link . '/sms/api/v1/websms/single', [], $params);
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

                    $responses[] = $response;
                } catch (\Exception $e) {
                    $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');
                }
            }
        } else {
            if ($template === null || empty($messageVars)) {
                throw new Exception('Message format is not correct');
            }
            foreach ($this->to as $number) {
                try {
                    $params['body'] = wp_json_encode([
                        'to'            => $this->to,
                        'message'       => [
                            'template_name'     => $template,
                            'language'          => 'en',
                            'type'              => 'template',
                            'body_params'       => $messageVars,
                            'header_params'     => $this->media,
                        ]
                    ]);

                    $response = $this->request('POST', $this->wsdl_link . '/whatsapp/api/v1/send', [], $params);
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

                    $responses[] = $response;
                } catch (\Exception $e) {
                    $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');
                }
            }
        }

        return json_encode($responses);
    }

    /**
     * Get available credit
     */
    public function GetCredit(): WP_Error|string
    {
        try {
            $token = $this->accessToken();

            if (is_wp_error($token)) {
                return $token;
            }

            $params = [
                'headers'   => [
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ]
            ];

            $response = $this->request('POST', $this->wsdl_link . '/sms/api/v1/balance', [], $params);

            return $response->balance;

        } catch (\Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }
    }

    /**
     * Get access token
     */
    public function accessToken(): WP_Error|string
    {
        try {
            if (empty($this->username) || empty($this->password)) {
                throw new Exception('Please enter Username and Password.');
            }

            $params = [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode([
                    'username'      => $this->username,
                    'password'      => $this->password,
                ]),
            ];

            $response = $this->request('POST', $this->wsdl_link . '/api/authentication/login', [], $params);

            return $response->accessToken;

        } catch (Exception $e) {
            return new WP_Error('authorization', $e->getMessage());
        }
    }
}