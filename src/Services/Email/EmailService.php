<?php

namespace WP_SMS\Services\Email;

use WP_Error;
use WP_SMS\Option;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * EmailService
 *
 * Facade around WordPress `wp_mail()` with plugin settings support.
 *
 * Features:
 * - Respects plugin settings (enabled/disabled, from name/email, reply-to).
 * - Normalizes headers (adds Content-Type, From, Reply-To if missing).
 * - Logs all send attempts via EmailLogger (debug details controlled by filter).
 * - Wraps result in an EmailResult object.
 *
 * Debug logging:
 *  - Controlled solely by the filter `wpsms_email_debug_logging_enabled` (default: false).
 *  - See EmailLogger::add() for how headers/body preview are captured when enabled.
 *
 * Usage example:
 *
 * use WP_SMS\Services\Email\EmailService;
 *
 * $result = EmailService::send([
 *     'to'          => 'user@example.com',
 *     'subject'     => 'Welcome!',
 *     'body'        => 'Thanks for signing up.',
 *     'headers'     => ['Content-Type: text/html; charset=UTF-8'],
 *     'attachments' => [ WP_SMS_URL . '/uploads/welcome.pdf' ],
 * ]);
 *
 * if ($result->success) {
 *     // Sent successfully
 * } else {
 *     error_log('Email failed: ' . $result->error);
 * }
 */
class EmailService
{
    /**
     * @param array $message
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
     * Load email settings from the plugin options.
     *
     * @return array
     */
    public static function getSettings()
    {
        $defaults = [
            'delivery_enabled' => false,
            'from_name'        => function_exists('get_bloginfo') ? get_bloginfo('name') : '',
            'from_email'       => function_exists('get_option') ? get_option('admin_email') : '',
            'reply_to'         => '',
        ];

        $settings = [];

        foreach ($defaults as $key => $fallback) {
            $value          = Option::getOption($key);
            $settings[$key] = ($value === '' || $value === null) ? $fallback : $value;
        }

        $settings['delivery_enabled'] = (bool)$settings['delivery_enabled'];

        return $settings;
    }

    /**
     * @param $headers
     * @return array|string[]
     */
    private static function normalizeHeaders($headers)
    {
        if (empty($headers)) {
            $normalizedHeaders = [];
        } elseif (is_array($headers)) {
            $normalizedHeaders = $headers;
        } else {
            $normalizedHeaders = [(string) $headers];
        }

        $hasContentType = false;
        foreach ($normalizedHeaders as $line) {
            if (stripos($line, 'content-type:') === 0) {
                $hasContentType = true;
                break;
            }
        }
        if (!$hasContentType) {
            $normalizedHeaders[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        return $normalizedHeaders;
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
     * @param string $name
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
     * Write a log row. Debug details (headers/body preview) are controlled by
     * the `wpsms_email_debug_logging_enabled` filter inside EmailLogger.
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
            'body'    => (string)$body,
            'success' => (bool)$base['success'],
            'error'   => $base['error'],
            'context' => ['ms' => (int)$durationMs],
        ];

        EmailLogger::add($row, $settings, $headers, $body, (array)$attachments);
    }
}
