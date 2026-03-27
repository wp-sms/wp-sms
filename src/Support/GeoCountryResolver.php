<?php

namespace WSms\Support;

defined('ABSPATH') || exit;

class GeoCountryResolver
{
    private const HEADERS = [
        'HTTP_CF_IPCOUNTRY',
        'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
        'HTTP_X_COUNTRY_CODE',
    ];

    private const SPECIAL_VALUES = ['T1', 'XX'];

    /**
     * Resolve visitor country from CDN/proxy headers.
     *
     * Returns an uppercase ISO 3166-1 alpha-2 code (e.g. 'US', 'GB'),
     * or null when no header is present or the value is a special
     * Cloudflare token (T1 = Tor, XX = unknown).
     */
    public static function resolve(): ?string
    {
        $raw = self::resolveRaw();

        if ($raw === null || in_array($raw, self::SPECIAL_VALUES, true)) {
            return null;
        }

        return $raw;
    }

    /**
     * Resolve the raw header value (uppercase, preserves T1/XX).
     *
     * Useful for audit/security contexts where Tor and unknown
     * origin markers carry meaningful signal.
     */
    public static function resolveRaw(): ?string
    {
        foreach (self::HEADERS as $header) {
            if (!empty($_SERVER[$header])) {
                $value = sanitize_text_field(wp_unslash($_SERVER[$header]));
                $value = strtoupper(trim($value));

                if (preg_match('/^[A-Z]{2}$/', $value) || in_array($value, self::SPECIAL_VALUES, true)) {
                    return $value;
                }
            }
        }

        return null;
    }
}
