<?php

namespace WPSmsTwoWay\Core;

class Core
{
    /**
     * @param $to
     * @param $message
     * @param false $is_flash
     * @param null $from
     * @return string|\WP_Error
     */
    public static function sendSMS($to, $message, $is_flash = false, $from = null)
    {
        if (is_string($to)) {
            if (strpos($to, ',')) {
                $to = explode(',', $to);
            }
        }
        $to = is_array($to) ? $to : [$to];

        return wp_sms_send($to, $message, $is_flash, $from);
    }
}
