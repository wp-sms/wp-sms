<?php

namespace WP_SMS\Utils;

use WP_SMS\BackgroundProcess\WP_Error;

class Sms
{
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

        $sms->isflash = isset($parameters['is_flash']) ? $parameters['is_flash'] : false;
        $sms->to      = $to;
        $sms->msg     = $parameters['msg'];
        $sms->media   = isset($parameters['mediaUrls']) ? $parameters['mediaUrls'] : [];

        if (isset($parameters['from']) && $parameters['from']) {
            $sms->from = $parameters['from'];
        }

        return $sms->SendSMS();
    }
}