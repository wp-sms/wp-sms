<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Exception;

class eazismspro extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://dashboard.eazismspro.com/sms/api";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;
    public $bulk_send = true;
    public $help = '';

    private $_responses = array(
        1000 => '1000 - Message submitted successfully',
        1002 => '1002 - SMS sending failed',
        1003 => '1003 - Insufficient balance',
        1004 => '1004 - Invalid API key',
        1005 => '1005 - Invalid Phone Number',
        1006 => '1006 - Invalid Sender ID. Sender ID must not be more than 11 Characters. Characters include white space.',
        1007 => '1007 - Message scheduled for later delivery',
        1008 => '1008 - Empty Message',
    );

    public function __construct()
    {
        parent::__construct();
        $this->has_key        = true;
        $this->validateNumber = "The recipient's phone in international format with the country code (you can omit the leading \"+\"). Example: Phone = 233240123456. You can specify multiple recipient numbers separated by commas. Example: Phone = 233240123456, 233240123457";
        $this->help           = "Visit <a href='https://dashboard.eazismspro.com'>dashboard.eazismspro.com</a> and click on 'GENERATE API' to create your API Key. This gateway does not use a username or password. <br>";
        $this->help           .= "<span style='color: red; font-weight: bold'>We also deliver messages worldwide. All you need to do is to prefix the right country code</span>. <br>";
        $this->help           .= "Visit <a href='https://eazismspro.com/blog/faqs-on-eazi-sms-pro-gateway-on-wp-sms-wordpress-plugin/'>Our FAQ</a>  for assistance";
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

        // Get the credit.
        $credit = $this->GetCredit();

        // Check gateway credit
        if (is_wp_error($credit)) {
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return $credit;
        }

        $to   = implode(",", $this->to);
        $text = iconv('cp1251', 'utf-8', $this->msg);

        try {
            $response = $this->request('GET', $this->wsdl_link, [
                'action'   => 'send-sms',
                'api_key'  => $this->options['gateway_key'],
                'from'     => $this->from,
                'sms'      => $text,
                'to'       => $to,
                'response' => 'json',
            ]);

            $response_code = $response->code ?? null;

            if (count($this->to) == 1) {
                if ($response_code != '1000') {
                    $message = $this->_responses[$response_code] ?? $response->message ?? 'Unknown error';
                    $this->log($this->from, $this->msg, $this->to, $message, 'error');
                    return new \WP_Error('send-sms', $message);
                }

                $this->log($this->from, $this->msg, $this->to, $this->_responses[$response_code]);
            }

            if (count($this->to) > 1) {
                $this->log($this->from, $this->msg, $this->to, $response->message ?? 'Bulk SMS sent');
            }

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

            return $response;
        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        // Check api key
        if (!$this->has_key) {
            return new \WP_Error('account-credit', esc_html__('API username or API password is not entered.', 'wp-sms'));
        }

        try {
            $response = $this->request('GET', $this->wsdl_link, [
                'action'   => 'check-balance',
                'api_key'  => $this->options['gateway_key'],
                'response' => 'json',
            ]);

            return $response->balance ?? $response;
        } catch (Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }
    }
}
