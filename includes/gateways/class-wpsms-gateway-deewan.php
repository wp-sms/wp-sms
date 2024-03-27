<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;

class deewan extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://apis.deewan.sa";
    public $tariff = "http://deewan.sa";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->has_key        = true;
        $this->validateNumber = "The phone number must contain only digits together with the country code. It should not contain any other symbols such as (+) sign.  Instead  of  plus  sign,  please  put  (00)" . PHP_EOL . "e.g seperate numbers with comma: 12345678900, 11222338844";
        $this->help           = "";
        $this->gatewayFields  = [
            'username' => [
                'id'   => 'gateway_username',
                'name' => 'Username',
                'desc' => 'Enter your username.',
            ],
            'has_key'  => [
                'id'   => 'gateway_has_key',
                'name' => 'API Key',
                'desc' => 'Enter your API Key.',
            ],
            'from'     => [
                'id'   => 'gateway_sender_id',
                'name' => 'Sender number',
                'desc' => 'Sender number or sender ID',
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

            $token  = $this->getGeneratedToken();
            $params = array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => "Bearer $token",
                ),
                'body'    => wp_json_encode([
                    'messageText' => $this->msg,
                    'senderName'  => $this->from,
                    'messageType' => 'text',
                    'recipients'  => implode(',', $this->to),
                ])
            );

            $response = $this->request('POST', "{$this->wsdl_link}/sms/v1/messages", [], $params, false);
            
            if (isset($response->error)) {
                throw new Exception($response->error->description);
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
            // Check username and password
            if (!$this->username || !$this->has_key) {
                throw new Exception(esc_html__('Username and password are required.', 'wp-sms'));
            }

            $token  = $this->getGeneratedToken();
            $params = array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => "Bearer $token",
                )
            );

            $response = $this->request('GET', "{$this->wsdl_link}/sms/v1/account/balance", [], $params, false);

            if (isset($response->error)) {
                throw new Exception($response->error->description);
            }

            return $response->data->Account->Credit;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }

    }

    private function getGeneratedToken()
    {
        $params = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'accept'       => 'application/json',
            ),
            'body'    => wp_json_encode([
                'userName' => $this->username,
                'apiKey'   => $this->has_key,
            ])
        );

        $response = $this->request('POST', "{$this->wsdl_link}/auth/v1/signin", [], $params, false);

        if (isset($response->error)) {
            throw new Exception(esc_html($response->error->description));
        }

        return $response->data->access_token;
    }
}
