<?php

namespace WP_SMS\Gateway;

class mtarget extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api-public.mtarget.fr/api-sms.json";
    public $tariff = "http://mtarget.fr/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "33xxxxxxxxx";
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

            $resultJSON = file_get_contents($this->wsdl_link . '?username=' . urlencode($this->username) . '&password=' . urlencode($this->password) . '&sender=' . urlencode($this->from) . '&msisdn=' . urlencode($to_list) . '&msg=' . urlencode($this->msg) . '&allowunicode=' . $allowunicode);

            try {
                $result = json_decode($resultJSON);
                foreach ($result->results as $message) {
                    if ($message->reason !== 'ACCEPTED') {
                        $success = false;
                    }
                }
            } catch (Exception $e) {
                $success = false;
            }

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $resultJSON);
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
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        // Using a legacy endpoint to check the remaining credit
        $result = file_get_contents("https://smswebservices.mtarget.fr/SmsWebServices/ServletSms?method=getAccountInformation&username=" . $this->username . "&password=" . $this->password);
        preg_match('/<CREDIT>([^<]+)<\/CREDIT>/', $result, $regex_match);

        $credit = (int)$regex_match[1];

        return $credit;
    }
}
