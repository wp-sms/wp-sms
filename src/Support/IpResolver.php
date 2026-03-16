<?php

namespace WSms\Support;

defined('ABSPATH') || exit;

class IpResolver
{
    private const HEADERS = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

    public static function resolve(): string
    {
        foreach (self::HEADERS as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));

                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Render an HTML paragraph with the client IP and a security warning.
     *
     * Returns empty string when the IP cannot be determined.
     */
    public static function renderIpWarningHtml(string $action): string
    {
        $ip = self::resolve();

        if ($ip === '') {
            return '';
        }

        return sprintf(
            '<p style="color:#666;font-size:0.9em;">'
            . __('%1$s from IP: %2$s. If you did not request this, your password may have been compromised.', 'wp-sms')
            . '</p>',
            esc_html($action),
            esc_html($ip),
        );
    }
}
