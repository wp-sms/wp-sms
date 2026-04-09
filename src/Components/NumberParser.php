<?php

namespace WP_SMS\Components;

use WP_Error;
use WP_SMS\Helper;
use WP_SMS\Option;

if (!defined('ABSPATH')) exit;

/**
 * A utility class for validating and normalizing phone numbers.
 */
class NumberParser
{
    private $rawPhoneNumber;
    private $normalizedPhoneNumber;
    private $validatedPhoneNumber;
    private $isInternationalInputEnabled;

    /**
     * @param string $phoneNumber
     */
    public function __construct($phoneNumber)
    {
        $this->rawPhoneNumber = $phoneNumber;

        $this->isInternationalInputEnabled = Option::getOption('international_mobile');
    }

    /**
     * Returns the validated phone number in international format.
     *
     * @return string|WP_Error
     */
    public function getValidNumber()
    {
        if (!empty($this->validatedPhoneNumber)) {
            return $this->validatedPhoneNumber;
        }

        $phoneNumber = $this->getNormalizedNumber();
        $example     = Helper::getPhoneFormatExample();
        // translators: %s is an example phone number formatted in the admin's locale.
        $exampleHint = sprintf(__('For example: %s', 'wp-sms'), $example);

        // Validate the phone number format
        if (!$this->isNumberFormatValid($phoneNumber)) {
            return new WP_Error(
                'invalid_number',
                sprintf(
                    /* translators: 1: the value the user submitted, 2: example formatted number */
                    __('Could not understand "%1$s" as a phone number. Please enter a complete phone number. %2$s', 'wp-sms'),
                    $this->rawPhoneNumber,
                    $exampleHint
                )
            );
        }

        // Return an error if + doesn't exists and "International Number Input" is enabled
        if ($this->isInternationalInputEnabled && strpos($phoneNumber, '+') !== 0) {
            return new WP_Error(
                'invalid_number',
                sprintf(
                    /* translators: %s: example formatted number */
                    __('The mobile number is missing a country code. Please include it (the leading +). %s', 'wp-sms'),
                    $exampleHint
                )
            );
        }

        // Validate length
        if (!$this->isLengthValid($phoneNumber)) {
            return new WP_Error(
                'invalid_length',
                sprintf(
                    /* translators: 1: the value the user submitted, 2: example formatted number */
                    __('"%1$s" does not look like a complete phone number. %2$s', 'wp-sms'),
                    $this->rawPhoneNumber,
                    $exampleHint
                )
            );
        }

        if ($this->isInternationalInputEnabled) {
            // Validate the country code
            if (!$this->isCountryCodeValid($phoneNumber)) {
                return new WP_Error(
                    'invalid_country_code',
                    sprintf(
                        /* translators: 1: the value the user submitted, 2: example formatted number */
                        __('The country code on "%1$s" is not allowed. %2$s', 'wp-sms'),
                        $this->rawPhoneNumber,
                        $exampleHint
                    )
                );
            }
        } else {
            // Manually add the country code
            $phoneNumber = $this->addSelectedCountryCode($phoneNumber);

            if (is_wp_error($phoneNumber)) {
                return $phoneNumber;
            }
        }

        $this->validatedPhoneNumber = $phoneNumber;
        return $this->validatedPhoneNumber;
    }

    /**
     * Returns the normalized/sanitized phone number by removing non-numeric characters (except +)
     * and stripping the trunk prefix (single leading 0) for local numbers.
     *
     * @return string
     */
    public function getNormalizedNumber()
    {
        if (!empty($this->normalizedPhoneNumber)) {
            return $this->normalizedPhoneNumber;
        }

        if (empty($this->rawPhoneNumber)) {
            return '';
        }

        $number = self::toEnglishNumerals($this->rawPhoneNumber);

        $this->normalizedPhoneNumber = preg_replace('/[^\d+]/', '', $number);

        // Convert international '00' prefix to '+' (e.g. '0044...' → '+44...')
        if (strpos($this->normalizedPhoneNumber, '00') === 0 && strpos($this->normalizedPhoneNumber, '+') !== 0) {
            $this->normalizedPhoneNumber = '+' . substr($this->normalizedPhoneNumber, 2);
        }
        // Strip a single leading trunk prefix '0' for local numbers (not numbers already starting with +)
        elseif (strpos($this->normalizedPhoneNumber, '+') !== 0 && strpos($this->normalizedPhoneNumber, '0') === 0) {
            $this->normalizedPhoneNumber = substr($this->normalizedPhoneNumber, 1);
        }

        return $this->normalizedPhoneNumber;
    }

    /**
     * Checks if the format of the phone number if valid.
     *
     * @param string $phoneNumber
     *
     * @return bool
     */
    public function isNumberFormatValid($phoneNumber)
    {
        $numericCheck = apply_filters('wp_sms_mobile_number_numeric_check', true);
        return !$numericCheck || is_numeric($phoneNumber);
    }

    /**
     * Checks if the phone number length is valid.
     *
     * @param string|null $phoneNumber
     *
     * @return bool
     */
    public function isLengthValid($phoneNumber = null)
    {
        if (empty($phoneNumber)) {
            $phoneNumber = $this->rawPhoneNumber;
        }

        $length    = strlen($phoneNumber);
        $minLength = Option::getOption('mobile_terms_minimum');
        $maxLength = Option::getOption('mobile_terms_maximum');

        if ($this->isInternationalInputEnabled || (!$minLength && !$maxLength)) {
            return $length >= 8 && $length <= 15;
        }

        return (!$minLength || $length >= $minLength) && (!$maxLength || $length <= $maxLength);
    }

    /**
     * Checks if the country code is valid based on the `countries.json` file and "Only Countries" option.
     *
     * @param string $phoneNumber
     *
     * @return bool
     */
    public function isCountryCodeValid($phoneNumber)
    {
        $allowedDialCodes = $this->getAllowedDialCodes();

        foreach ($allowedDialCodes as $dialCode) {
            if (strpos($phoneNumber, $dialCode) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns allowed dial codes based on the "Only Countries" option.
     *
     * @return array
     */
    public function getAllowedDialCodes()
    {
        $allDialCodes = wp_sms_countries()->getAllDialCodesByCode();

        /**
         * "Only Countries" option status.
         *
         * @var array
         */
        $onlyCountries = Option::getOption('international_mobile_only_countries');

        // Return all dial codes if "Only Countries" option is empty
        if (empty($onlyCountries)) {
            return array_merge(...array_values($allDialCodes));
        }

        // Otherwise, return only dial codes for the allowed countries
        $allowedDialCodes = [];
        foreach ($onlyCountries as $countryCode) {
            if (!empty($allDialCodes[$countryCode])) {
                // Some countries have multiple dial codes (e.g. Puerto Rico)
                $allowedDialCodes = array_merge($allowedDialCodes, $allDialCodes[$countryCode]);
            }
        }

        return $allowedDialCodes;
    }

    /**
     * Adds selected "Country Code Prefix" option manually.
     *
     * @param string $phoneNumber
     *
     * @return string
     */
    public function addSelectedCountryCode($phoneNumber)
    {
        // If the number already starts with '+', it already has a country code — return as-is
        if (strpos($phoneNumber, '+') === 0) {
            return $phoneNumber;
        }

        $selectedCountryCode = Option::getOption('mobile_county_code');
        if (empty($selectedCountryCode) || $selectedCountryCode === '0') {
            return new WP_Error('missing_country_code', __('Default Country Code is not configured. Please set it in WP SMS Phone settings.', 'wp-sms'));
        }

        // Strip the leading + for the digits-only version of the country code
        $countryCodeDigits = ltrim($selectedCountryCode, '+');

        // If number already starts with the country code digits (without +), just ensure + prefix
        if (strpos($phoneNumber, $countryCodeDigits) === 0) {
            return '+' . $phoneNumber;
        }

        // Prepend country code (which already includes +)
        return $selectedCountryCode . $phoneNumber;
    }

    /**
     * Convert non-English numerals to English numerals.
     *
     * @param string $number Input number string
     * @return string Number with English numerals only
     */
    public static function toEnglishNumerals($number)
    {
        return strtr($number, array(
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'
        ));
    }
    /**
     * Checks if the phone number exists in usermeta.
     *
     * @param string $phoneNumber
     * @param int|null $userId
     *
     * @return bool|WP_Error
     */
    public static function isDuplicateInUsermeta($phoneNumber, $userId = null)
    {
        global $wpdb;

        $mobileField = Helper::getUserMobileFieldName();
        if (empty($mobileField)) {
            return new WP_Error('invalid_mobile_field', __('This user mobile field is invalid.', 'wp-sms'));
        }

        // Fuzzy match across all known surface forms so legacy non-canonical rows still
        // register as duplicates after the E.164 migration.
        $clause = Helper::buildMobileInClause($phoneNumber);
        $params = array_merge([$mobileField], $clause['params']);
        $sql    = "SELECT * FROM `{$wpdb->prefix}usermeta` WHERE `meta_key` = %s AND `meta_value` IN ({$clause['placeholders']})";

        if ($userId) {
            $sql     .= ' AND `user_id` != %d';
            $params[] = $userId;
        }

        $query = $wpdb->prepare($sql, $params);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query is built via $wpdb->prepare() above
        return !empty($wpdb->get_results($query));
    }

    /**
     * Checks if the phone number exists in the `sms_subscriber` table.
     *
     * @param string $phoneNumber
     * @param int|null $groupID
     * @param int|null $subscribeId
     *
     * @return bool
     */
    public static function isDuplicateInSubscribers($phoneNumber, $groupID = null, $subscribeId = null)
    {
        global $wpdb;

        // Fuzzy match across all known surface forms so legacy non-canonical rows still
        // count as duplicates after the E.164 migration.
        $clause = Helper::buildMobileInClause($phoneNumber);
        $params = $clause['params'];
        $sql    = "SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE `mobile` IN ({$clause['placeholders']})";

        if ($groupID) {
            $sql     .= ' AND `group_ID` = %d';
            $params[] = $groupID;
        }
        if ($subscribeId) {
            $sql     .= ' AND `id` != %d';
            $params[] = $subscribeId;
        }

        $query = $wpdb->prepare($sql, $params);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query is built via $wpdb->prepare() above
        $result = $wpdb->get_row($query);

        // Check if result exists and it has an active status
        return (!empty($result) && $result->status == '1');
    }
}
