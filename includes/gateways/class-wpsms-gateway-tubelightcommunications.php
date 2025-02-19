<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

class tubelightcommunications extends Gateway
{
    private     $wsdl_link = "https://portal.tubelightcommunications.com";
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
        $this->help = __('<b>SMS Route</b>: Please follow this format in your messages: <pre>message|template_id</pre><br><b>WhatsApp Route</b>: Please follow this format in your messages: <pre>var1:var2:var3:var4|template_name</pre>', 'wp-sms');
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
            // Get access token
            $token = $this->accessToken();
            if (is_wp_error($token)) {
                throw new Exception('Authorization Error.');
            }

            // Get template and message body
            $templateMessage = $this->getTemplateIdAndMessageBody();
            $template = $templateMessage['template_id'] ?? null;
            $message = $templateMessage['message'] ?? null;

            if (empty($message)) {
                throw new Exception('Invalid Message Format');
            }

            $messageVars = explode(':', $message);

            $params = [
                'headers'   => [
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ],
            ];

            // Process SMS
            if ($this->route === 'sms') {
                return $this->sendSMSMessage($params, $template, $message);
            }

            // Process WhatsApp
            if ($this->route === 'whatsapp') {
                return $this->sendWhatsAppMessage($params, $template, $messageVars);
            }

            throw new Exception('Invalid Route.');

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    /**
     * Get available credit
     */
    public function GetCredit()
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
    private function accessToken()
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

    private function sendSMSMessage($params, $template, $message)
    {
        $params['body'] = wp_json_encode(array_map(function ($number) use ($message, $template) {
                return [
                    'sender'        => $this->from,
                    'mobileNo'      => $number,
                    'messageType'   => 'TEXT',
                    'messages'      => $message,
                    'tempId'        => $template ?? '',
                ];
            }, $this->to));
        
        $response = $this->request('POST', $this->wsdl_link . '/sms/api/v1/websms/bulksend', [], $params);

        $this->log($this->from, $this->msg, $this->to, $response);
        do_action('wp_sms_send', $response);

        return $response;
    }

    /**
     * @throws Exception
     */
    private function sendWhatsAppMessage($params, $template, $messageVars)
    {
        if (empty($template) || empty($messageVars)) {
            throw new Exception('Invalid Message Format');
        }

        foreach ($this->to as $number) {
            try {
                $params['body'] = wp_json_encode([
                    'to'      => [$number],
                    'message' => [
                        'template_name' => $template,
                        'type'          => 'template',
                        'body_params'   => $messageVars,
                        'header_params' => $this->media,
                    ]
                ]);
                
                $response = $this->request('POST', $this->wsdl_link . '/whatsapp/api/v1/send', [], $params);

                $this->log($this->from, $this->msg, $this->to, $response);
                do_action('wp_sms_send', $response);

                return $response;
            } catch (\Exception $e) {
                $this->log($this->from, $this->msg, $number, $e->getMessage(), 'error');
            }
        }
    }
}