<?php

namespace WP_SMS\Gateway;

class tubelightcommunications extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://webpostservice.com";
    public $documentUrl = 'https://wp-sms-pro.com/resources/tubelight-communications-sms-gateway-configuration/';
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $template_id = '';
    public $base_url = '';
    public $has_key = true;
    public $validateNumber = "919811xxxxxx";
    public $gatewayFields = [
        'has_key'     => [
            'id'   => 'gateway_key',
            'name' => 'API Key',
            'desc' => 'Enter API key of gateway.',
        ],
        'from'        => [
            'id'   => 'gateway_sender_id',
            'name' => 'Sender number',
            'desc' => 'Sender number or sender ID',
        ],
        'template_id' => [
            'id'   => 'gateway_template_id',
            'name' => 'Template ID',
            'desc' => 'Enter your DLT Template ID (Optional)',
        ]
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return string|\WP_Error
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

            // Get the credit.
            $credit = $this->GetCredit();

            // Check gateway credit
            if (is_wp_error($credit)) {
                throw new \Exception($credit->get_error_message());
            }

            $response = $this->request('POST', "{$this->wsdl_link}/sendsms_v1.0/chakra.php", [], [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body'    => json_encode([
                    'authentication' => [
                        'key'     => $this->has_key,
                        'version' => '1.0',
                        'channel' => '0',
                    ],
                    'message'        => [
                        'smsdata' => array_map(function ($number) {
                            return [
                                'destination' => $number,
                                'source'      => $this->from,
                                'type'        => 'TEXT',
                                'content'     => urlencode($this->msg),
                                'tempId'      => $this->template_id,
                            ];
                        }, $this->to),
                    ],
                ])
            ]);

            $errorMessage = $this->getErrorMessage($response);

            if ($errorMessage) {
                throw new \Exception($errorMessage);
            }

            // Log the result
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

    /**
     * @return int|\WP_Error
     */
    public function GetCredit()
    {
        try {

            $response = $this->request('GET', "{$this->wsdl_link}/sendsms_v2.0/checkbalance.php", [
                'apikey' => urlencode($this->has_key),
            ]);

            $errorMessage = $this->getErrorMessage($response);

            if ($errorMessage) {
                throw new \Exception($errorMessage);
            }

            return $response;

        } catch (\Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }
    }

    private function getErrorMessage($errorCode)
    {
        if (is_array($errorCode)) {
            foreach ($errorCode as $item) {
                if ($item->code == 200) {
                    return false;
                } else {
                    return $item->cause;
                }
            }
        }

        switch ($errorCode) {
            case 'ERR_LOGIN':
                return 'The response code is returned if user login is invalid in System.';
            case 'ERR_USERNAME_PASSWORD':
                return 'The response code is returned if username or password is not formed well; that is for invalid characters other than [a-z, A-Z, 0-9, -].';
            case 'ERR_MSGID':
                return 'The response code is returned in case of wrong or invalid Message id.';
            case 'DELIVRD':
                return 'Delivered to destination';
            case 'UNDELIV':
                return 'Message is undeliverable';
            case 'EXPIRED':
                return 'Validity period has expired';
            case 'REJECTD':
                return 'Message is in rejected state';
            case 'DELETED':
                return 'Message is deleted due to flood control mechanism.';
            case 'UNKNOWN':
                return 'Message is in unknown state';
            default:
                return false;
        }
    }
}