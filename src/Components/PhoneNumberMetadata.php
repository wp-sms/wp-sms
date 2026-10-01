<?php

namespace WP_SMS\Components;

if (!defined('ABSPATH')) exit;

/**
 * Checks whether an international phone number is a real number for the country
 * its calling code points at.
 *
 * The patterns come from the libphonenumber metadata that already ships inside the
 * bundled intl-tel-input utils (resources/vendor-js/intel/utils.js). They are extracted
 * into resources/json/phone-number-metadata.json by bin/build-phone-metadata.mjs, so
 * the PHP side can validate numbers without adding a phone library to the plugin.
 */
class PhoneNumberMetadata
{
    /**
     * @var array|null Calling code => list of region entries.
     */
    private static $metadata = null;

    /**
     * @var array Memoized validity results, keyed by digits.
     */
    private static $validCache = [];

    /**
     * @return array
     */
    private static function getMetadata()
    {
        if (self::$metadata !== null) {
            return self::$metadata;
        }

        self::$metadata = [];
        $file           = WP_SMS_DIR . 'resources/json/phone-number-metadata.json';

        if (is_readable($file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                self::$metadata = $decoded;
            }
        }

        return self::$metadata;
    }

    /**
     * Whether the metadata file could be loaded. Callers that change data must do
     * nothing when it is not.
     *
     * @return bool
     */
    public static function isAvailable()
    {
        return !empty(self::getMetadata());
    }

    /**
     * Whether a calling code (digits only, e.g. "1", "44") is known.
     *
     * @param string $callingCode
     * @return bool
     */
    public static function hasCallingCode($callingCode)
    {
        $metadata = self::getMetadata();
        return $callingCode !== '' && isset($metadata[(string) $callingCode]);
    }

    /**
     * Whether an international number (with or without the leading +) is a valid
     * number for the country its calling code belongs to.
     *
     * @param string $number e.g. "+79161234567"
     * @return bool
     */
    public static function isValidNumber($number)
    {
        $digits = preg_replace('/\D/', '', (string) $number);
        if ($digits === '' || $digits[0] === '0') {
            return false;
        }

        if (isset(self::$validCache[$digits])) {
            return self::$validCache[$digits];
        }

        $valid = false;
        // Calling codes are one to three digits and no code is a prefix of another.
        for ($length = 1; $length <= 3 && $length < strlen($digits); $length++) {
            $callingCode = substr($digits, 0, $length);
            if (self::hasCallingCode($callingCode)) {
                $valid = self::isValidForCallingCode($callingCode, substr($digits, $length));
                break;
            }
        }

        if (count(self::$validCache) > 5000) {
            self::$validCache = [];
        }

        return self::$validCache[$digits] = $valid;
    }

    /**
     * Whether a national number is valid for any region that uses the calling code.
     *
     * @param string $callingCode    Digits only, e.g. "1"
     * @param string $nationalNumber Digits only, e.g. "7065810032"
     * @return bool
     */
    public static function isValidForCallingCode($callingCode, $nationalNumber)
    {
        $metadata = self::getMetadata();
        if (!isset($metadata[$callingCode]) || $nationalNumber === '' || !ctype_digit((string) $nationalNumber)) {
            return false;
        }

        foreach ($metadata[$callingCode] as $region) {
            if (empty($region['general']) || !self::fullMatch($region['general'], $nationalNumber)) {
                continue;
            }

            foreach ((array) ($region['types'] ?? []) as $pattern) {
                if (self::fullMatch($pattern, $nationalNumber)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $pattern
     * @param string $subject
     * @return bool
     */
    private static function fullMatch($pattern, $subject)
    {
        return @preg_match('~\A(?:' . $pattern . ')\z~', $subject) === 1;
    }
}
