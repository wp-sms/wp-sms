<?php

namespace WP_SMS\Gateway;

class texti extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://traffic.sales.lv/API:0.16/";
    public $tariff = "https://texti.fi/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "37198765432, 37112345678";
        $this->has_key        = true;
        $this->gatewayFields  = [
            'from'    => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender number',
                'desc' => 'Alphanumeric sender ID text, e.g. your brand name.',
            ],
            'has_key' => [
                'id'   => 'gateway_key',
                'name' => 'API Key',
                'desc' => 'Please enter your API Key, you can generate it by logging into your <a href="https://traffic.sales.lv/lv/Settings/Users/">Settings</a>.'
            ]
        ];
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
            $response = $this->request('POST', $this->wsdl_link, [], [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body'    => json_encode([
                    'APIKey'     => $this->has_key,
                    'Command'    => 'Send',
                    'Sender'     => $this->from,
                    'Content'    => $this->msg,
                    'Recipients' => array_map(function ($number) {
                        return [$number, $this->msg];
                    }, $this->to),
                ])
            ]);

            if (isset($response->Error)) {
                throw new \Exception($this->getErrorMessage($response->Error));
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
            if (!$this->has_key) {
                throw new \Exception(__('The API Key for this gateway is not set', 'wp-sms-pro'));
            }

            return 1;

        } catch (\Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }
    }

    private function getErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case 'InvalidRecipients':
                return 'Incorrect recipient number (in case of a single number) or incorrectly assembled recipient array.';
            case 'InvalidSender':
                return 'Sender ID doesn’t exist or isn’t available';
            case 'InvalidCountryCode':
                return 'Incorrect or unsupported country code, if specified';
            case 'ContentTooLong':
                return 'Long SMS message has more than 7 content parts, See <a target="_blank" href="https://help.sales.lv/en/article/sms-content-and-length-114li95/">SMS content and length</a>';
            case 'QuotaExceeded':
                return 'Account quota has been exceeded';
            case 'InvalidShortenLink':
                return 'An invalid website address was provided in the ShortenLinksOverride parameter.';
            default:
                return $errorCode;
        }
    }
}