<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use WP_SMS\Exceptions\SmsGatewayException;

class avalpayam extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://www.avalpayam.com/class/sms/wssimple/server.php?wsdl";
    private $client = null;
    public $tariff = "http://avalpayam.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    private $soapAvailable = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxx";

        if (!class_exists('\SoapClient')) {
            $this->soapAvailable = false;
        } else {
            $this->soapAvailable = true;
        }
    }

    public function SendSMS()
    {
        try {
            if (!$this->soapAvailable) {
                throw SmsGatewayException::soapNotAvailable();
            }

            if (!$this->username || !$this->password) {
                throw SmsGatewayException::invalidCredentials();
            }

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

            $client = new \SoapClient($this->wsdl_link, [
                'encoding'   => 'UTF-8',
                'exceptions' => true,
                'trace'      => true,
            ]);

            $result = $client->__soapCall("SendSMS", [
                'Username'         => $this->username,
                'Password'         => $this->password,
                'SenderNumber'     => $this->from,
                'RecipientNumbers' => $this->to,
                'Message'          => $this->msg,
                'Type'             => 'normal'
            ]);

            if ($result) {
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
            }

            // Log th result
            $this->log($this->from, $this->msg, $this->to, $result, 'error');

            throw SmsGatewayException::gatewayError(wp_json_encode($result, JSON_UNESCAPED_UNICODE));

        } catch (SmsGatewayException $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');
            return new \WP_Error('gateway-error', $e->getMessage());

        } catch (\SoapFault $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');
            return new \WP_Error('soap-fault', $e->getMessage());

        } catch (\Throwable $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');
            return new \WP_Error('unexpected-error', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        try {
            if (!$this->soapAvailable) {
                throw SmsGatewayException::soapNotAvailable();
            }

            if (!$this->username || !$this->password) {
                throw SmsGatewayException::invalidCredentials();
            }

            $client = new \SoapClient($this->wsdl_link, [
                'encoding'   => 'UTF-8',
                'exceptions' => true,
            ]);

            $result = $client->__soapCall("GetCredit", [
                'Username' => $this->username,
                'Password' => $this->password
            ]);

            return $result;

        } catch (SmsGatewayException $e) {
            return new \WP_Error('gateway-error', $e->getMessage());

        } catch (\SoapFault $e) {
            return new \WP_Error('soap-fault', $e->getMessage());

        } catch (\Throwable $e) {
            return new \WP_Error('unexpected-error', $e->getMessage());
        }
    }
}