<?php

namespace WP_SMS\Gateway;


use Exception;
use WP_Error;

class bulkgate extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://portal.bulkgate.com/api/2.0";
    public $tariff = "https://www.bulkgate.com/";
    public $flash = "false";
    public $isflash = false;
    public $unitrial = true;
    public $supportIncoming = true;
    public $unit;
    public $smsUnicode = false;
    public $viberSender = '';
    public $senderIdValue = '';

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send     = true;
        $this->has_key       = true;
        $this->documentUrl   = 'https://wp-sms-pro.com/resources/bulkgate-gateway-configuration/';
        $this->help          = '<a href="https://portal.bulkgate.com/application/">Get your own Application ID and Application Token</a>';
        $this->gatewayFields = [
            'username'      => [
                'id'   => 'gateway_username',
                'name' => 'Application ID',
                'desc' => 'Enter your Application ID',
            ],
            'password'      => [
                'id'   => 'gateway_password',
                'name' => 'Application Token',
                'desc' => 'Enter your Application Token',
            ],
            'from'          => [
                'id'      => 'gateway_sender_id',
                'name'    => 'Sender ID type',
                'desc'    => 'Please select what is your Sender ID type',
                'type'    => 'select',
                'options' => [
                    'gSystem'  => 'System Number',
                    'gShort'   => 'Short Code',
                    'gText'    => 'Text Sender',
                    'gOwn'     => 'Own Number',
                    'gProfile' => 'BulkGate Sender ID Profile',
                    'gMobile'  => 'MobileConnect'
                ]
            ],
            'smsUnicode'    => [
                'id'      => 'gateway_unicode',
                'name'    => 'Unicode',
                'desc'    => 'When you activate Unicode, it allows you to use characters outside of the standard GSM character set.',
                'type'    => 'select',
                'options' => [
                    'off' => 'Off',
                    'on'  => 'On',
                ]
            ],
            'senderIdValue' => [
                'id'   => 'gateway_sender_id_value',
                'name' => 'Sender ID Value',
                'desc' => 'Enter value for Text Sender/Own Number/Profile/MobileConnect',
            ],
            'viberSender'   => [
                'id'   => 'gateway_viber_sender',
                'name' => 'Viber Sender',
                'desc' => 'Set your registered Viber Sender',
            ]
        ];
    }

    public function SendSMS()
    {
        /**
         * Modify sender id
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {

            if (count($this->to) > 1) {
                $number          = $this->to;
                $apiUrl          = "{$this->wsdl_link}/advanced/promotional";
                $checkDuplicates = 'same_text';
            } else {
                $number          = $this->to[0];
                $apiUrl          = "{$this->wsdl_link}/advanced/transactional";
                $checkDuplicates = true;
            }

            $body = [
                'application_id'      => $this->username,
                'application_token'   => $this->password,
                'application_product' => 'wp_sms',
                'number'              => $number,
                'duplicates_check'    => $checkDuplicates,
                'channel'             => [],
            ];

            if (strlen(trim((string)$this->viberSender)) > 0) {
                $body['channel']['viber'] = [
                    'sender'     => $this->viberSender,
                    'expiration' => 3600,
                    'text'       => $this->msg
                ];
            }

            $body['channel']['sms'] = [
                'sender_id'       => $this->from,
                'sender_id_value' => $this->senderIdValue,
                'text'            => $this->msg,
                'unicode'         => $this->smsUnicode
            ];

            $params = [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body'    => wp_json_encode($body)
            ];

            $response = $this->request('POST', $apiUrl, [], $params, false);

            if (isset($response->error) && $response->error) {
                throw new Exception($response->error);
            }

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response);

            /*
             * Run hook after send sms.
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
            // Check Api key
            if (!$this->from or !$this->has_key) {
                throw new Exception(esc_html__('Application id and Application token for this gateway are require.', 'wp-sms'));
            }

            $arguments = [
                'application_id'      => $this->username,
                'application_token'   => $this->password,
                'application_product' => 'wp_sms',
            ];

            $response = $this->request('GET', "{$this->wsdl_link}/advanced/info", $arguments, [], false);

            if (isset($response->error) && $response->error) {
                throw new Exception($response->error);
            }

            return $response->data->credit;

        } catch (\Throwable $e) {
            return new \WP_Error('get-credit', $e->getMessage());
        }
    }
}

