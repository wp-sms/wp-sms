<?php

namespace WP_SMS\Components;

use WP_Error;
use WP_SMS\Helper;
use WP_SMS\Option;

/**
 * A utility class for validating and normalizing phone numbers.
 */
class NumberParser
{
    private $rawPhoneNumber;
    private $normalizedPhoneNumber;
    private $validatedPhoneNumber;

    /**
     * @param string $phoneNumber
     */
    public function __construct($phoneNumber)
    {
        $this->rawPhoneNumber = $phoneNumber;

    }

    /**
     * Returns the validated phone number in international format.
     *
     * @return string|WP_Error
     */
    /**
     * Validate and return the cleaned mobile number.
     *
     * @return string|WP_Error
     */
    /**
     * Validate and return the cleaned mobile number.
     *
     * @return string|WP_Error
     */
    public function getValidNumber()
    {
        if (!empty($this->validatedPhoneNumber)) {
            return $this->validatedPhoneNumber;
        }

        $phoneNumber = $this->getNormalizedNumber();

        // Validate the phone number format
        if (!$this->isNumberFormatValid($phoneNumber)) {
            return new WP_Error('invalid_number', __('Invalid Mobile Number.', 'wp-sms'));
        }

        // Enforce presence of country code (+ prefix is mandatory in E.164)
        if (strpos($phoneNumber, '+') !== 0) {
            return new WP_Error('invalid_number', __('The mobile number must include a country code.', 'wp-sms'));
        }

        // Validate E.164 length (8–15 characters)
        if (!$this->isLengthValid($phoneNumber)) {
            return new WP_Error('invalid_length', __('The mobile number length is invalid.', 'wp-sms'));
        }

        // Validate the country code itself
        if (!$this->isCountryCodeValid($phoneNumber)) {
            return new WP_Error('invalid_country_code', __('The mobile number is not valid for your country.', 'wp-sms'));
        }

        $this->validatedPhoneNumber = $phoneNumber;
        return $this->validatedPhoneNumber;
    }


    /**
     * Returns the normalized/sanitized phone number by removing the leading zero and non-numeric characters (except +).
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
        $this->normalizedPhoneNumber = ltrim($this->normalizedPhoneNumber, '0');

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
     * Validate phone number length against E.164 standard.
     *
     * @param string|null $phoneNumber Optional phone number to validate.
     *                                  Defaults to $this->rawPhoneNumber.
     * @return bool
     */
    public function isLengthValid($phoneNumber = null)
    {
        if (empty($phoneNumber)) {
            $phoneNumber = $this->rawPhoneNumber;
        }

        $length = strlen($phoneNumber);

        // E.164 standard: minimum 8, maximum 15 digits.
        return $length >= 8 && $length <= 15;
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
         * @var array $onlyCountries
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
     * @deprecated 6.9.15  The “Country Code Prefix” setting has been retired.
     */
    public function addSelectedCountryCode($phoneNumber)
    {
        // Mark the method deprecated in runtime and, if WP_DEBUG is on,
        // trigger a _doing_it_wrong() notice in the error log.
        _deprecated_function(
            __METHOD__,                // Function name.
            '6.9.15'                        // Version in which it was deprecated.
        );

        $selectedCountryCode = Option::getOption('mobile_county_code');
        if (empty($selectedCountryCode)) {
            if (
                strpos($this->rawPhoneNumber, '0') === 0 &&
                substr_count($this->rawPhoneNumber, '0', 0, 2) === 1
            ) {
                return $this->rawPhoneNumber;
            }

            return $phoneNumber;
        }

        // Add leading + if not exists
        if (strpos($phoneNumber, '+') !== 0) {
            $phoneNumber = "+$phoneNumber";
        }

        // Ensure country code hasn't been added already
        if (strpos($phoneNumber, $selectedCountryCode) !== 0) {
            // Remove leading + if exists
            $phoneNumber = str_replace('+', '', $phoneNumber);

            // Add selected country code
            $phoneNumber = $selectedCountryCode . $phoneNumber;
        }

        return $phoneNumber;
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

        $query = $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}usermeta` WHERE `meta_key` = %s AND `meta_value` = %s", $mobileField, $phoneNumber);
        if ($userId) {
            $query .= $wpdb->prepare(' AND `user_id` != %d', $userId);
        }

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

        $query = $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE `mobile` = %s", $phoneNumber);
        if ($groupID) {
            $query .= $wpdb->prepare(' AND `group_ID` = %d', $groupID);
        }
        if ($subscribeId) {
            $query .= $wpdb->prepare(' AND `id` != %d', $subscribeId);
        }

        $result = $wpdb->get_row($query);

        // Check if result exists and it has an active status
        return (!empty($result) && $result->status == '1');
    }
}
