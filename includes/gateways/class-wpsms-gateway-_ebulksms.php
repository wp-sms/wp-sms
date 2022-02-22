<?php

namespace WP_SMS\Gateway;

class _ebulksms extends \WP_SMS\Gateway
{

    public $wsdl_link = "http://api.ebulksms.com";
    public $tariff = "http://ebulksms.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "23470XXXXXXXX,23480XXXXXXXX,23490XXXXXXXX,23481XXXXXXXX";

        // Enable api key
        $this->has_key = true;
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
        //$this->to = apply_filters( 'wp_sms_to', $this->to );
        $this->to = $this->formatMobileNumbers($this->to);

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

        $response = wp_remote_get($this->wsdl_link . "/sendsms?username=" . $this->username . "&apikey=" . $this->has_key . "&sender=" . $this->from . "&messagetext=" . urlencode($this->msg) . "&flash=" . $this->isflash . "&recipients=" . implode(',', $this->to));

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Ger response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Check response code
        if ($response_code == '200') {
            if (strpos($response['body'], 'SUCCESS') !== false) {
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
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

                return new \WP_Error('send-sms', $response['body']);
            }

        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->has_key) {
            return new \WP_Error('account-credit', __('The Username/Password for this gateway is not set', 'wp-sms'));
        }

        // Get response
        $response = wp_remote_get($this->wsdl_link . '/balance/' . $this->username . '/' . $this->has_key);

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            return $response['body'];

        } else {
            return new \WP_Error('account-credit', $response['body']);
        }
    }

    private function formatMobileNumbers($strnumbers, $country_code = '234', $separator = ',')
    {
        $cleanrecipients = array();
        if (!empty($strnumbers)) {
            $validnumbers = array();
            $strnumbers   = is_array($strnumbers) ? implode(',', $strnumbers) : $strnumbers;
            $strnumbers   = str_replace(array("\r\n", "\r", "\n"), ',', $strnumbers);

            $regExp = "/[0-9]{10,15}/";
            if (preg_match_all($regExp, $strnumbers, $validnumbers)) {
                $validnumbers = $validnumbers[0];
                foreach ($validnumbers as $mobilenumber) {
                    if (substr($mobilenumber, 0, 1) == '0') {
                        $mobilenumber = $country_code . substr($mobilenumber, 1);
                    } elseif (substr($mobilenumber, 0, 4) == '2340') {
                        $mobilenumber = $country_code . substr($mobilenumber, 4);
                    } elseif (strlen($mobilenumber) < 11 && $country_code == '234') {
                        $mobilenumber = $country_code . $mobilenumber;
                    }
                    if (strlen($mobilenumber) < 10 || strlen($mobilenumber) > 15) {
                        continue;
                    }
                    if ((substr($mobilenumber, 0, 3) == "234") && strlen($mobilenumber) != 13) {
                        continue;
                    }
                    $cleanrecipients[] = $mobilenumber;
                }
                $cleanrecipients = array_merge(array_unique($cleanrecipients));
            }
        } else {
            return '';
        }

        return $cleanrecipients;
    }
}
