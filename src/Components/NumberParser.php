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
    private $isInternationalInputEnabled;

    /**
     * Holds merged country codes and names.
     * 
     * @var array
     */
    public static $mergedCountries = [];

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
     * @todo This should be converted to a static method.
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

        // Return an error if + doesn't exists and "International Number Input" is enabled
        if ($this->isInternationalInputEnabled && ! self::startsWithPlus($phoneNumber)) {
            return new WP_Error('invalid_number', __('The mobile number doesn\'t contain the country code.', 'wp-sms'));
        }

        // Validate length
        if (!$this->isLengthValid($phoneNumber)) {
            return new WP_Error('invalid_length', __('The mobile number length is invalid.', 'wp-sms'));
        }

        if ($this->isInternationalInputEnabled) {
            // Validate the country code
            if (!$this->isCountryCodeValid($phoneNumber)) {
                return new WP_Error('invalid_country_code', __('The mobile number is not valid for your country.', 'wp-sms'));
            }
        } else {
            // Manually add the country code
            $phoneNumber = $this->addSelectedCountryCode($phoneNumber);
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

        $this->normalizedPhoneNumber = preg_replace('/[^\d+]/', '', $this->rawPhoneNumber);
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
        $selectedCountryCode = Option::getOption('mobile_county_code');

        if (empty($selectedCountryCode)) {
            return $this->detectCountryCode($phoneNumber);
        }

        // Add leading + if not exists
        if (!self::startsWithPlus($phoneNumber)) {
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
     * Extracts or validates the country code from a phone number.
     * 
     * @param string $phoneNumber The phone number to process
     * @param bool $exportNumber If true, returns the number without country code
     * @return string The country code or number without country code based on $exportNumber
     */
    public static function getPhoneNumberCountry( $phoneNumber, $exportNumber = false ) {
        if (empty(self::$mergedCountries)) {
            self::$mergedCountries = wp_sms_countries()->getCountriesMerged();
        }
        
        foreach (self::$mergedCountries as $countryCode => $countryName) {
            if (strpos($phoneNumber, $countryCode) !== 0) {
                continue;
            }

            if ( $exportNumber ) {
                return substr($phoneNumber, strlen($countryCode));
            }

            return $countryCode;
        }

        return '';
    }

    /**
     * Detects and validates the country code in a phone number.
     * 
     * @param string $phoneNumber The phone number to process
     * @return string|WP_Error Validated phone number or error if invalid
     */
    public function detectCountryCode($phoneNumber) {      
        if (self::startsWithPlus($phoneNumber)) {
            if (self::getPhoneNumberCountry($phoneNumber)) {
                return $phoneNumber;
            }

            return new WP_Error('invalid_number', __('The mobile number doesn\'t contain the country code.', 'wp-sms'));
        }

        $phoneNumber = "+$phoneNumber";

        $mainNumber = self::getPhoneNumberCountry($phoneNumber, true);
        
        if (! $this->isLengthValid($mainNumber)) {
            return new WP_Error('invalid_length', __('The mobile number length is invalid.', 'wp-sms'));
        }

        return $phoneNumber;
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

    /**
     * Checks if a phone number string starts with a plus sign.
     * 
     * @param string|null $number The phone number to check
     * @return bool Returns true if number starts with '+', false otherwise
     */
    public static function startsWithPlus($number) {
        return !empty($number) && strpos($number, '+') === 0;
    }

    /**
     * Prepares phone number variations for database queries.
     * Creates an array of possible formats of the number for searching.
     * 
     * @param string $number The phone number to process
     * @return array Array of phone number variations
     */
    public static function prepareMobileNumberQuery($number)
    {
        $metaValue[]    = $number;
        $numberWithPlus = '+' . $number;
        
        // Check if number is international format or not and add country code to meta value
        if (!self::startsWithPlus($number)) {
            $metaValue[] = $numberWithPlus;
            $number      = $numberWithPlus;
        } else {
            $metaValue[] = ltrim($number, '+');
        }

        $metaValue[] = self::getPhoneNumberCountry($number, true);

        return $metaValue;
    }
}
