<?php

namespace WP_SMS\Components;

use WP_Error;
use WP_SMS\Helper;

if (!defined('ABSPATH')) exit;

/**
 * Class Sms
 *
 * This class is responsible for sending SMS messages.
 */
class Sms
{
    /**
     * Sends an SMS message.
     *
     * @param array $parameters An array containing the parameters for sending the SMS message.
     *                          The array should have the following keys:
     *                          - to (required) : The recipient(s) of the SMS. This can be either a single mobile number or an array of mobile numbers.
     *                          - is_flash : Optional. A boolean indicating whether the SMS is a flash message. Default is false.
     *                          - msg (required) : The content of the SMS message.
     *                          - mediaUrls : Optional. An array of URLs of media files to be included in the SMS.
     *                          - from : Optional. The sender of the SMS.
     *
     * @return mixed The result of sending the SMS message.
     *               If the SMS is sent successfully, the response from the SMS service provider is returned.
     *               If there is an error in sending the SMS, a WP_Error object with the 'invalid_mobile_number' code is returned.
     */
    public static function send($parameters)
    {
        global $sms;

        if (is_null($sms) && function_exists('wp_sms_initial_gateway')) {
            $sms = wp_sms_initial_gateway();
        }

        $to = $parameters['to'];

        // Backward compatibility
        if (!is_array($to)) {
            $to = array($to);
        }

        // Unset empty values from $to array
        $to = array_filter($to, function ($mobile) {
            return $mobile !== '' && $mobile !== '0';
        });

        // Backward compatibility
        if (count($to) === 0 or empty($to) or sizeof($to) === 0) {
            return new WP_Error('invalid_mobile_number', __('Mobile number not found, please make sure the mobile field in settings page is configured.', 'wp-sms'));
        }

        // Check if the message is empty (trim whitespace first)
        if (empty($parameters['msg']) || trim($parameters['msg']) === '') {
            return new WP_Error('empty_message', __('Message content cannot be empty. Please provide a valid SMS message.', 'wp-sms'));
        }

        // Last chokepoint before gateway dispatch — guarantees that everything written to the
        // outbox by Logger::logOutbox is canonical, regardless of how the caller built $to.
        // Short codes pass through unchanged so marketing campaigns still reach 4-6 digit codes.
        $to = array_map([Helper::class, 'normalizeToE164WithShortCodeGuard'], $to);

        $sms->isflash          = isset($parameters['is_flash']) ? $parameters['is_flash'] : false;
        $sms->to               = Helper::removeDuplicateNumbers($to);
        $sms->msg              = $parameters['msg'];
        $sms->media            = isset($parameters['mediaUrls']) ? $parameters['mediaUrls'] : [];
        $sms->messageVariables = isset($parameters['messageVariables']) ? $parameters['messageVariables'] : [];

        if (isset($parameters['from']) && $parameters['from']) {
            $sms->from = $parameters['from'];
        }

        return self::dispatch($sms);
    }

    /**
     * Longest time, in seconds, a gateway may keep a stream-based request
     * (SOAP WSDL download, SOAP call, file_get_contents) open while sending.
     */
    const GATEWAY_SOCKET_TIMEOUT = 20;

    /**
     * Calls the gateway's SendSMS() so that a provider outage can never take
     * the page down with it.
     *
     * Many gateways talk SOAP: when the provider's server is down, PHP throws a
     * SoapFault (or an Error) from inside SendSMS(). Uncaught, that turns a
     * failed SMS into a fatal error on checkout, login or form submit. Here it
     * becomes a WP_Error and an outbox entry instead, like any other failure.
     *
     * @param object $sms Gateway instance with to/msg/from already set.
     *
     * @return mixed Gateway result, or WP_Error when the gateway threw.
     */
    public static function dispatch($sms)
    {
        $previousTimeout = ini_get('default_socket_timeout');
        $lowerTimeout    = (int)$previousTimeout <= 0 || (int)$previousTimeout > self::GATEWAY_SOCKET_TIMEOUT;

        if ($lowerTimeout) {
            @ini_set('default_socket_timeout', (string)self::GATEWAY_SOCKET_TIMEOUT);
        }

        try {
            return $sms->SendSMS();
        } catch (\Throwable $e) {
            /* translators: %s: error message returned by the SMS provider */
            $message = sprintf(__('The SMS provider could not be reached: %s', 'wp-sms'), $e->getMessage());

            if (method_exists($sms, 'log')) {
                try {
                    $sms->log($sms->from, $sms->msg, $sms->to, $message, 'error');
                } catch (\Throwable $logError) {
                    // Logging must never turn a handled failure back into a fatal one.
                }
            }

            return new WP_Error('send-sms', $message);
        } finally {
            if ($lowerTimeout) {
                @ini_set('default_socket_timeout', (string)$previousTimeout);
            }
        }
    }
}