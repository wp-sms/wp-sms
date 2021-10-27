<?php

namespace WP_SMS\Gateway;

class primotexto extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.primotexto.com/v2/";
    public $tariff = "http://www.primotexto.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "Format: 0600000000, +33600000000";
        $this->help           = 'Vous devez génerer une clé depuis votre <a href="https://www.primotexto.com/webapp/#/developer/keys">interface Primotexto</a> pour pouvoir utiliser l\'API.';
        $this->has_key        = true;
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

        // Get the credit.
        $credit = $this->GetCredit();

        // Check gateway credit
        if (is_wp_error($credit)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return $credit;
        }

        $api    = $this->has_key;
        $msg    = $this->msg;
        $from   = $this->from;
        $result = array();
        $error  = 0;

        try {
            foreach ($this->to as $number) {

                try {
                    $args = array(
                        'headers' => array(
                            'X-Primotexto-ApiKey' => $api,
                            'Content-Type'        => 'application/json; charset=UTF-8',
                        ),
                        'body'    => json_encode(
                            array(
                                'number'  => trim($number),
                                'message' => $msg,
                                'sender'  => $from
                            ))
                    );
                    // Authentication
                    $response = wp_remote_post($this->wsdl_link . "notification/messages/send", $args);

                    // check response have error or not
                    if (is_wp_error($response)) {
                        $result[$number] = $response->get_error_message();
                    }
                    // Ger response code
                    $response_code = wp_remote_retrieve_response_code($response);

                    // Decode response
                    $response = json_decode($response['body']);

                    // Check response code
                    if ($response_code == '200') {
                        if (isset($response->snapshotId)) {
                            $result[$number] = $response;
                        } else {
                            $result[$number] = array('code' => $response->code, 'error' => $response->error);
                            $error++;
                        }
                    } else {
                        $result[$number] = array('code' => $response->code, 'error' => $response->error);
                        $error++;
                    }
                } catch (\Exception $e) {
                    // Log the result
                    $result[$number] = $e->getMessage();
                    $error++;
                }
            }

            // Check if results have error or not
            if ($error > 0) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result, 'error');

                return new \WP_Error('send-sms', $result);
            }

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $result);

            return $result;
        } catch (\Exception $e) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('The API Key for this gateway is not set', 'wp-sms'));
        }
        // Authentication
        $args = array(
            'headers' => array(
                'X-Primotexto-ApiKey' => $this->has_key,
            )
        );

        $result = wp_remote_get($this->wsdl_link . "account/stats", $args);

        $json = json_decode($result['body']);

        if (isset($json->error)) {
            return new \WP_Error('credit', $json->error);
        }

        return $json->credits;
    }
}