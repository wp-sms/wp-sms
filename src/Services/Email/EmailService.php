<?php

namespace WP_SMS\Services\Email;

use WP_Error;
use WP_SMS\Option;

if (!defined('ABSPATH')) {
    exit;
}

class EmailService
{
    /**
     * @param $message
     * @return EmailResult
     */
    public static function send($message)
    {
        $to          = $message['to'] ?? '';
        $subject     = $message['subject'] ?? '';
        $body        = $message['body'] ?? '';
        $headers     = $message['headers'] ?? [];
        $attachments = $message['attachments'] ?? [];

        $settings = self::getSettings();
        $enabled  = (bool)apply_filters('wp_sms_email_enabled', !empty($settings['delivery_enabled']), $message);

        if (!$enabled) {
            self::logAttempt([
                'to'      => $to,
                'subject' => $subject,
                'success' => false,
                'error'   => 'Email sending is disabled.',
            ], $settings, $headers, $body, 0, $attachments);

            return new EmailResult(false, 'Email sending is disabled.', ['reason' => 'disabled']);
        }

        $headers = self::normalizeHeaders($headers);
        $headers = self::applySenderDefaults($headers, $settings);
        $headers = apply_filters('wp_sms_email_headers', $headers, $message, $settings);
        $args    = [
            'to'          => $to,
            'subject'     => $subject,
            'message'     => $body,
            'headers'     => $headers,
            'attachments' => $attachments,
        ];
        $args    = apply_filters('wp_sms_email_pre_send_args', $args, $message, $settings);

        // Capture failure reasons
        $lastError = null;
        $listener  = function ($wpError) use (&$lastError) {
            $lastError = $wpError;
        };
        add_action('wp_mail_failed', $listener);

        $started = microtime(true);
        $success = false;

        try {
            $success = function_exists('wp_mail')
                ? wp_mail($args['to'], $args['subject'], $args['message'], $args['headers'], $args['attachments'])
                : false;
        } catch (\Exception $e) {
            $lastError = new WP_Error('exception', $e->getMessage());
        }

        remove_action('wp_mail_failed', $listener);

        $errorMsg = $success ? null : ($lastError instanceof WP_Error ? $lastError->get_error_message() : 'Unknown error');
        $duration = (int)round((microtime(true) - $started) * 1000);

        self::logAttempt([
            'to'      => $args['to'],
            'subject' => $args['subject'],
            'success' => (bool)$success,
            'error'   => $errorMsg,
        ], $settings, $args['headers'], $args['message'], $duration, $args['attachments']);

        $result = new EmailResult($success, $errorMsg, ['ms' => $duration]);

        do_action('wp_sms_email_post_send', $result, $args, $message, $settings);

        return $result;
    }

    /**
     * @return mixed|null
     */
    public static function getSettings()
    {
        $defaults = [
            'delivery_enabled'       => false,
            'from_name'     => function_exists('get_bloginfo') ? get_bloginfo('name') : '',
            'from_email'    => function_exists('get_option') ? get_option('admin_email') : '',
            'reply_to'      => '',
            'debug_logging' => false,
        ];

        $settings = Option::getOptions();

        if (!is_array($settings)) {
            $settings = [];
        }

        $settings = wp_parse_args($settings, $defaults);
        return array_intersect_key($settings, $defaults);
    }

    /**
     * @param $headers
     * @return array|string[]
     */
    private static function normalizeHeaders($headers)
    {
        $h = [];
        if (empty($headers)) {
            $h = [];
        } elseif (is_array($headers)) {
            $h = $headers;
        } else {
            $h = [(string)$headers];
        }

        $hasContentType = false;
        foreach ($h as $line) {
            if (stripos($line, 'content-type:') === 0) {
                $hasContentType = true;
                break;
            }
        }
        if (!$hasContentType) {
            $h[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        return $h;
    }

    /**
     * @param $headers
     * @param $settings
     * @return mixed
     */
    private static function applySenderDefaults($headers, $settings)
    {
        $hasFrom    = false;
        $hasReplyTo = false;

        foreach ($headers as $line) {
            $lower = strtolower($line);
            if (strpos($lower, 'from:') === 0) {
                $hasFrom = true;
            }
            if (strpos($lower, 'reply-to:') === 0) {
                $hasReplyTo = true;
            }
        }

        $fromName  = $settings['from_name'] ?? (function_exists('get_bloginfo') ? get_bloginfo('name') : '');
        $fromEmail = $settings['from_email'] ?? (function_exists('get_option') ? get_option('admin_email') : '');
        $replyTo   = $settings['reply_to'] ?? '';

        if (!$hasFrom && $fromEmail) {
            $headers[] = sprintf('From: %s <%s>', self::sanitizeName($fromName), sanitize_email($fromEmail));
        }
        if (!$hasReplyTo && $replyTo) {
            $headers[] = sprintf('Reply-To: %s', sanitize_email($replyTo));
        }
        return $headers;
    }

    /**
     * @param $name
     * @return string
     */
    private static function sanitizeName($name)
    {
        $name = (string)$name;
        $name = wp_kses($name, []);
        $name = trim(preg_replace('/[\\r\\n]+/', ' ', $name));
        return $name;
    }

    /**
     * Write a log row (minimal by default; more when debug_logging enabled).
     *
     * @param array $base
     * @param array $settings
     * @param array $headers
     * @param string $body
     * @param int $durationMs
     * @param array $attachments
     */
    private static function logAttempt($base, $settings, $headers, $body, $durationMs, $attachments = [])
    {
        $row = [
            'time'    => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
            'to'      => is_array($base['to']) ? implode(',', $base['to']) : (string)$base['to'],
            'subject' => (string)$base['subject'],
            'success' => (bool)$base['success'],
            'error'   => $base['error'],
            'context' => ['ms' => (int)$durationMs]
        ];

        if (!empty($settings['debug_logging'])) {
            $row['headers'] = $headers;
            $preview        = trim(wp_strip_all_tags((string)$body));
            $preview        = function_exists('mb_substr') ? mb_substr($preview, 0, 200) : substr($preview, 0, 200);
            $row['body']    = $preview;
        }

        EmailLogger::add($row, $settings, $headers, $body, (array)$attachments);
    }
}
