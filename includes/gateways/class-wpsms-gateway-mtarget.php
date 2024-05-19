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
        $this->help           = "The mobile number must include the <b>country code</b>. To automatically add the country code to the number, set the Country Code Prefix option from the Settings - General section. For <b>bulk send</b>, set Delivery Method to Batch SMS Queue.";
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
            // Check for a valid connection to the gateway
            $credit = $this->GetCredit();
            if (is_wp_error($credit)) {
                $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');
                return $credit;
            }

            $allowUnicode = 'false';
            if (isset($this->options['send_unicode']) && $this->options['send_unicode']) {
                $allowUnicode = 'true';
            }

            $recipients = implode(',', $this->to);

            $params = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body'    => [
                    'username'     => $this->username,
                    'password'     => $this->password,
                    'sender'       => $this->from,
                    'msisdn'       => $recipients,
                    'msg'          => $this->msg,
                    'allowunicode' => $allowUnicode,
                ]
            ];

            $response = $this->request('POST', $this->wsdl_link . 'messages', [], $params, false);

            $succeed = $failed = [];
            foreach ($response->results as $message) {
                if ($message->code == 0) {
                    $succeed[$message->msisdn] = $message;
                } else {
                    $failed[$message->msisdn] = $message;
                }
            }

            if (count($succeed) > 0) {
                $this->log($this->from, $this->msg, array_keys($succeed), $succeed);
            }

            if (count($failed) > 0) {
                $this->log($this->from, $this->msg, array_keys($failed), $failed, 'error');
            }

            if ($failed) {
                return new WP_Error('send-sms', 'The SMS did not send for this number(s): ' . implode('<br/>', array_keys($failed)) . ' See the response on Outbox.');
            }

            do_action('wp_sms_send', $response);

            return $response;

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');
            return new WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username || !$this->password) {
            return new WP_Error('account-credit', esc_html__('API username or API password is not entered.', 'wp-sms'));
        }

        try {
            $params = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body'    => [
                    'username' => $this->username,
                    'password' => $this->password,
                ],
            ];

            $response = $this->request('POST', $this->wsdl_link . 'balance', [], $params, false);

            if (isset($response->error)) {
                throw new Exception($response->error);
            }

            if (isset($response->results[0]->reason)) {
                throw new Exception($response->results[0]->reason);
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
