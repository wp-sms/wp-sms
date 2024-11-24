<?php

namespace WP_SMS\Components;

use WP_Error;
use WP_SMS\Helper;

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

        // Check if the message is empty
        if (empty($parameters['msg'])) {
            return new WP_Error('empty_message', __('Message content cannot be empty. Please provide a valid SMS message.', 'wp-sms'));
        }

        $sms->isflash = isset($parameters['is_flash']) ? $parameters['is_flash'] : false;
        $sms->to      = Helper::removeDuplicateNumbers($to);
        $sms->msg     = $parameters['msg'];
        $sms->media   = isset($parameters['mediaUrls']) ? $parameters['mediaUrls'] : [];

        if (isset($parameters['from']) && $parameters['from']) {
            $sms->from = $parameters['from'];
        }

        return $sms->SendSMS();
    }
}