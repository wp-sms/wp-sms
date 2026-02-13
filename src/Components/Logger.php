<?php

namespace WP_SMS\Components;

if (!defined('ABSPATH')) exit;

use WP_SMS\Option;

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
        global $wpdb;

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

        $result = null;
        $store  = Option::getOption('store_outbox_messages');

        if ($store) {
            $result = $wpdb->insert($wpdb->prefix . "sms_send", array(
                'date'      => WP_SMS_CURRENT_DATE,
                'sender'    => $sender,
                'message'   => $message,
                'recipient' => implode(',', $to),
                'response'  => var_export($response, true),
                'media'     => serialize($media),
                'status'    => $status,
            ));
        }

        /**
         * Fire after send sms
         */
        do_action('wp_sms_log_after_save', $result, $sender, $message, $to, $response, $status, $media);

        return $result;
    }

    /**
     * The main logging function
     *
     * @param string $message The message to be logged.
     * @param string $level The log level (e.g., 'info', 'warning', 'error'). Default is 'info'.
     * @uses error_log
     */
    public static function log($message, $level = 'info')
    {
        if (is_array($message)) {
            $message = wp_json_encode($message);
        }

        $logLevel = strtoupper($level);

        // Log when debug is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[WP SMS] [%s]: %s', $logLevel, $message));
        }
    }
}