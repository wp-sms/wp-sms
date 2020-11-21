<?php

namespace WP_SMS\Gateway;

use WP_Error;

class altiria extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://www.altiria.net/api/http";
    public $tariff = "http://www.altiria.net";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = '“346xxxxxxxx (international format without + or 00)” for "International format without + or 00 (346xxxxxxxx for Spain, 52xxxxxxxxx por Mexico, 57xxxxxxxxx for Colombia etc)”';
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

        $body = array(
            'cmd'      => 'sendsms',
            'login'    => $this->username,
            'passwd'   => $this->password,
            'msg'      => stripslashes($this->msg),
            'senderId' => $this->from,
            'source'   => 'wpsms'
        );

        if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
            $body['encoding'] = true;
        }

        $destination = '';
        foreach ($this->to as $number) {
            $destination .= '&dest=' . $number;
        }

        $destination = ltrim($destination, '&');
        $response    = wp_remote_post($this->wsdl_link . '?' . $destination, [
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'
            ),
            'body'    => $body
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        if (200 != wp_remote_retrieve_response_code($response)) {
            return new WP_Error('account-credit', $response['body']);
        }

        $arrayResponse = explode("\n", $response['body']);
        foreach ($arrayResponse as $item) {
            if ($item == '') continue;

            $to = [$this->getDestinationFromString($item)];

            if (strstr($item, 'ERROR')) {
                $errorNumber  = $this->getErrorNumberFromString($item);
                $errorMessage = $this->getErrorMessage($errorNumber);

                $this->log($this->from, $this->msg, $to, $errorMessage, 'error');
            } else {
                $this->log($this->from, $this->msg, $to, $item);

                /**
                 * Run hook after send sms.
                 *
                 * @param string $result result output.
                 * @since 2.4
                 *
                 */
                do_action('wp_sms_send', $response['body']);
            }
        }

        return $response['body'];
    }

    public function GetCredit()
    {
        $body = array(
            'cmd'    => 'getcredit',
            'login'  => $this->username,
            'passwd' => $this->password,
        );

        $response = wp_remote_post($this->wsdl_link, [
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'
            ),
            'body'    => $body
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        if (200 != wp_remote_retrieve_response_code($response)) {
            return new WP_Error('account-credit', $response['body']);
        }

        if (strstr($response['body'], 'ERROR')) {
            $errorMessage = $this->getErrorNumberFromString($response['body']);
            return new WP_Error('account-credit', $this->getErrorMessage($errorMessage));
        }

        preg_match('/.*OK credit\(0\):(.*?)$/', $response['body'], $match);

        if (isset($match[1])) {
            return $match[1];
        }

        return $response['body'];
    }

    private function getErrorNumberFromString($string)
    {
        preg_match('/errNum:([\d]+)/', $string, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    private function getDestinationFromString($string)
    {
        preg_match('/dest:([a-zA-Z0-9_.-]+)/', $string, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    private function getErrorMessage($responseError)
    {
        switch ($responseError) {
            case '001':
                $message = 'Internal error. Please contact technical support';
                break;
            case '010':
                $message = 'Error in the telephone number format';
                break;
            case '011':
                $message = 'Error in sending of the command parameters or incorrect codification The length of the message exceeds the maximum allowed length The HTTP request uses an invalid character codification There are not valid recipients to send the message Duplicated recipient';
                break;
            case '013':
                $message = 'The length of the message exceeds the maximum allowed length';
                break;
            case '014':
                $message = 'The HTTP request uses an invalid character codification';
                break;
            case '015':
                $message = 'There are not valid recipients to send the message';
                break;
            case '016':
                $message = 'Duplicated recipient';
                break;
            case '017':
                $message = 'Empty message';
                break;
            case '020':
                $message = 'Authentication error';
                break;
            case '022':
                $message = 'The selected originator for the message is not valid';
                break;
            case '030':
                $message = 'The URL and the message exceed the maximum allowed length';
                break;
            case '031':
                $message = 'The length of the URL is incorrect';
                break;
            case '032':
                $message = 'The URL contains not allowed characters';
                break;
            case '033':
                $message = 'The SMS destination port is incorrect';
                break;
            case '034':
                $message = 'The SMS source port is incorrect';
                break;
            default:
                $message = $responseError;
                break;
        }
        return $message;
    }
}