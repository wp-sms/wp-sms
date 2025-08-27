<?php


namespace WP_SMS\Services\Email;

use WP_SMS\Components\Logger as OutboxLogger;

if (!defined('ABSPATH')) {
    exit;
}

class EmailLogger
{
    /**
     * @param array $row
     * @param array $settings
     * @param array $headers
     * @param string $body
     * @param array $attachments
     * @return bool|int|\mysqli_result|resource|null
     */
    public static function add(array $row, array $settings = [], array $headers = [], $body = '', array $attachments = [])
    {
        $row = array_merge([
            'time'    => gmdate('Y-m-d H:i:s'),
            'to'      => '',
            'subject' => '',
            'success' => false,
            'error'   => null,
            'context' => ['ms' => 0],
        ], $row);

        $debug = self::isDebugEnabled($row, $settings, $headers, $body);

        $debugHeaders = $debug
            ? (isset($row['headers']) ? (array)$row['headers'] : (array)$headers)
            : [];
        $bodyPreview  = isset($row['body']) ? (string)$row['body'] : self::previewBody($body);

        [$from_name, $from_email] = self::extractSender($debugHeaders, [
            'from_name'  => $settings['from_name'] ?? '',
            'from_email' => $settings['from_email'] ?? '',
        ]);


        $senderLabel = $from_name ?: $from_email ?: 'email';

        $messageForLog = self::formatMessageForLog(
            (string)$row['subject'],
            $bodyPreview,
            $debugHeaders
        );

        $response = [
            'time'    => (string)$row['time'],
            'to'      => (string)$row['to'],
            'success' => (bool)$row['success'],
            'error'   => $row['error'],
            'context' => is_array($row['context']) ? $row['context'] : ['ms' => (int)$row['context']],
        ];

        $toArray = array_filter(array_map('trim', explode(',', (string)$row['to'])));

        try {
            return OutboxLogger::logOutbox(
                $senderLabel,
                $messageForLog,
                $toArray,
                $response,
                $row['success'] ? 'success' : 'failed',
                $attachments,
                'email'
            );
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('[WP_SMS EmailLogger] Failed to write log: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Filter-driven debug flag (headers/body preview).
     *
     * Filter: wpsms_email_debug_logging_enabled
     *
     * @param array $row
     * @param array $settings
     * @param array $headers
     * @param string $body
     * @return bool
     */
    private static function isDebugEnabled(array $row, array $settings, array $headers, $body): bool
    {
        return (bool) apply_filters(
            'wpsms_email_debug_logging_enabled',
            false,
            $row,
            $settings,
            $headers,
            (string) $body
        );
    }

    /**
     * @param array $headers
     * @param array $settings
     * @return array
     */
    private static function extractSender(array $headers, array $settings)
    {
        foreach ($headers as $line) {
            if (stripos($line, 'from:') === 0) {
                $fromLine = trim(substr($line, 5));
                if (preg_match('/^(?:"?([^"]*)"?\s)?<?([^<>@\s]+@[^<>@\s]+)>?$/', $fromLine, $m)) {
                    $name  = isset($m[1]) ? trim($m[1]) : '';
                    $email = isset($m[2]) ? sanitize_email($m[2]) : '';
                    if ($email) {
                        return [$name, $email];
                    }
                }
                break;
            }
        }

        $name  = $settings['from_name'] ?? '';
        $email = $settings['from_email'] ?? '';
        return [$name, sanitize_email($email)];
    }

    /**
     * @param $subject
     * @param $bodyPreview
     * @param array $includeHeaders
     * @return string
     */
    private static function formatMessageForLog($subject, $bodyPreview, array $includeHeaders)
    {
        $lines   = [];
        $lines[] = 'Subject: ' . (string)$subject;

        if ($includeHeaders) {
            $lines[] = '';
            $lines[] = 'Headers:';
            foreach ($includeHeaders as $h) {
                $lines[] = '  ' . trim($h);
            }
        }

        if ($bodyPreview !== '') {
            $lines[] = '';
            $lines[] = 'Body (preview):';
            $lines[] = (string)$bodyPreview;
        }

        return implode("\n", $lines);
    }

    /**
     * @param $body
     * @return false|string
     */
    private static function previewBody($body)
    {
        $txt = trim(wp_strip_all_tags((string)$body));
        if (function_exists('mb_substr')) {
            return mb_substr($txt, 0, 200);
        }
        return substr($txt, 0, 200);
    }
}