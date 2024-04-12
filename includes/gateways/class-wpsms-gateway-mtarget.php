<?php

namespace WP_SMS\Gateway;

use Exception;
use WP_Error;
use WP_SMS\Gateway;

/**
 * mtarget Gateway Class
 *
 * Website: https://mtarget.fr/
 * API Doc: https://developers.mtarget.fr
 *
 * @package WP_SMS\Gateway
 */
class mtarget extends Gateway
{
    private $wsdl_link = "https://api-public-2.mtarget.fr/";
    public $tariff = "http://mtarget.fr/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->bulk_send      = true;
        $this->validateNumber = esc_html__('Number of the recipient with country code (+44...) or in international format (0044 ...)', 'wp-sms');
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

        if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
            $allowunicode = 'true';
        } else {
            $allowunicode = 'false';
        }

        $success = true;

        // We want to send as few requests as we can
        $msisdns_sublists = array_chunk($this->to, 500);
        foreach ($msisdns_sublists as $sublist) {
            $to_list = '';
            foreach ($sublist as $to) {
                $to_list .= $to . ',';
            }
            // Check credit for the gateway
            if (!$this->GetCredit()) {
                $this->log($this->from, $this->msg, $this->to, $this->GetCredit(), 'error');

                return;
            }

            $arguments = [
                'username'     => $this->username,
                'password'     => $this->password,
                'sender'       => $this->from,
                'msisdn'       => $to_list,
                'msg'          => $this->msg,
                'allowunicode' => $allowunicode,
            ];

            $result = $this->request('POST', $this->wsdl_link . 'messages', $arguments);

            try {
                foreach ($result->results as $message) {
                    if ($message->reason !== 'ACCEPTED') {
                        $success = false;
                    }
                }
            } catch (Exception $e) {
                $success = false;
            }

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);
        }

        if ($success) {
            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $result);

            return $result;
        }
        // Log the result
        $this->log($this->from, $this->msg, $this->to, $this->GetCredit(), 'error');

        return new \WP_Error('send-sms', $result);
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username || !$this->password) {
            return new WP_Error('account-credit', esc_html__('API username or API password is not entered.', 'wp-sms'));
        }

        try {
            $arguments = [
                'username' => $this->username,
                'password' => $this->password,
            ];

            $response = $this->request('POST', $this->wsdl_link . 'balance', $arguments, [], false);

            if ($response->error) {
                throw new Exception($response->error);
            }

            if ($response->type == 'unavailable') {
                throw new Exception('Username or password is incorrect!');
            }

            if (!isset($response->amount)) {
                throw new Exception('Invalid response!');
            }

            return $response->amount;
        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }
}
