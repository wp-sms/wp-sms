<?php

namespace WP_SMS\Components;

class Logger
{
    /**
     * @param $sender
     * @param $message
     * @param $to
     * @param $response
     * @param $status
     * @param $media
     * @return bool|int|\mysqli_result|resource|null
     */
    public static function logOutbox($sender, $message, $to, $response, $status = 'success', $media = array())
    {
        /**
         * Backward compatibility
         * @todo Remove this if the length of the sender is increased in database
         */
        if (strlen($sender) > 20) {
            $sender = substr($sender, 0, 20);
        }

        if (!is_array($to)) {
            $to = [$to];
        }

        global $wpdb;
        $result = $wpdb->insert($wpdb->prefix . "sms_send", array(
            'date'      => WP_SMS_CURRENT_DATE,
            'sender'    => $sender,
            'message'   => $message,
            'recipient' => implode(',', $to),
            'response'  => var_export($response, true),
            'media'     => serialize($media),
            'status'    => $status,
        ));

        /**
         * Fire after send sms
         */
        do_action('wp_sms_log_after_save', $result, $sender, $message, $to, $response, $status, $media);

        return $result;
    }
}