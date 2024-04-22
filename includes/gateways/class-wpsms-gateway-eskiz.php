<?php

namespace WP_SMS\Gateway;

class eskiz extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://notify.eskiz.uz/api";
    public $tariff = "https://eskiz.uz";
    public $unitrial = false;
    public $unit;
    public $flash = "false";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->validateNumber = "";
        $this->help           = "Enter your API username and password.";
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

            $token     = $this->GetToken();
            $dataToken = $token->data->token;

            foreach ($this->to as $number) {
                $arguments = [
                    'headers' => array(
                        'Authorization' => "Bearer {$dataToken}"
                    ),
                    'body'    => [
                        'mobile_phone' => $number,
                        'message'      => $this->msg,
                        'from'         => $this->from,
                    ]
                ];

                $response[] = $this->request('POST', "{$this->wsdl_link}/message/sms/send", [], $arguments);
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

        } catch (\Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {

        try {
            // Check username and password
            if (!$this->username or !$this->password) {
                throw new \Exception(__('The username or password is not set.', 'wp-sms-pro'));
            }

            $token     = $this->GetToken();
            $dataToken = $token->data->token;
            $arguments = [
                'headers' => array(
                    'Authorization' => "Bearer {$dataToken}"
                ),
            ];

            $response = $this->request('GET', "{$this->wsdl_link}/auth/user", [], $arguments);

            return $response->balance;

        } catch (\Throwable $e) {
            return new \WP_Error('get-credit', $e->getMessage());
        }

    }

    //Get Eskiz Token
    public function GetToken()
    {
        $arguments = [
            'body' => [
                'email'    => $this->username,
                'password' => $this->password
            ]
        ];

        $response = $this->request('POST', "{$this->wsdl_link}/auth/login", [], $arguments);

        return $response;
    }
}