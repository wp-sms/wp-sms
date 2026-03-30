<?php

namespace WSms\Support;

use WSms\Exception\ValidationException;

defined('ABSPATH') || exit;

final class PhoneValidator
{
    public const E164_PATTERN = '/^\+[1-9]\d{1,14}$/';

    public static function stripFormatting(string $phone): string
    {
        return preg_replace('/[\s\-\(\)\.]+/', '', $phone);
    }

    /**
     * Clean formatting and validate E.164. Returns null if invalid.
     */
    public static function toE164(string $phone): ?string
    {
        $cleaned = self::stripFormatting($phone);

        return preg_match(self::E164_PATTERN, $cleaned) ? $cleaned : null;
    }

    /**
     * Validate E.164 or throw. Returns the cleaned phone.
     */
    public static function assertE164(string $phone): string
    {
        $result = self::toE164($phone);

        if ($result === null) {
            throw ValidationException::field(
                'phone',
                __('Please enter a valid phone number with country code (e.g. +12025551234).', 'wp-sms'),
            );
        }

        return $result;
    }

    public static function isE164(string $phone): bool
    {
        return self::toE164($phone) !== null;
    }

    /**
     * REST API phone argument definition with validation.
     */
    public static function restArg(): array
    {
        return [
            'required' => false,
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => static fn($v) => $v === '' || self::isE164($v)
                || new \WP_Error('invalid_phone', __('Please enter a valid phone number with country code (e.g. +12025551234).', 'wp-sms')),
        ];
    }
}
