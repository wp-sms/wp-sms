<?php

namespace WP_SMS\Utils;

class Countries
{
    private static $countries;
    private static $countriesFileDir = WP_SMS_DIR . 'assets/countries.json';

    /**
     * Initializes the `$countries` array.
     *
     * @return  void
     * @throws  \Exception
     */
    private static function initializeCountries()
    {
        // Already initialized
        if (!empty(self::$countries)) return;

        self::$countries = self::getCountriesFile();
        if (empty(self::$countries))
            throw new \Exception(__('Invalid countries.json file, check logs!', 'wp-sms'));

        self::addFullInfoField();
    }

    /**
     * Tries to get the content of the `countries.json` file as an associative array.
     *
     * @return  array   Countries list as an associative array, or empty array on error.
     */
    private static function getCountriesFile()
    {
        $jsonData = wp_json_file_decode(self::$countriesFileDir, ['associative' => true]);
        return $jsonData !== null ? $jsonData : [];
    }

    /**
     * Adds the `fullInfo` field to the countries array.
     *
     * Country name + country native name + dial code will be displayed in this field.
     *
     * @return  void
     */
    private static function addFullInfoField()
    {
        if (empty(self::$countries)) return;

        foreach (self::$countries as $index => $country) {
            if (!empty($country['fullInfo'])) continue;

            $fullInfo = $country['name'];
            if ($country['name'] !== $country['nativeName'])
                $fullInfo .= " ({$country['nativeName']})";
            $fullInfo .= ' (' . implode($country['allDialCodes']) . ')';

            self::$countries[$index]['fullInfo'] = $fullInfo;
        }
    }

    /**
     * Returns the countries array.
     *
     * @param   string  $field  Pluck the array with this field.
     * @param   string  $key    Which field should be used as key? (Works only when `$field` is not empty)
     *
     * @return  array           The plucked or the complete array, or empty array on error.
     */
    public static function getCountries($field = '', $key = '')
    {
        try {
            self::initializeCountries();
        } catch (\Exception $e) {
            return [];
        }

        if (!empty($field) && !empty($key))
            return wp_list_pluck(self::$countries, $field, $key);

        return self::$countries;
    }

    /**
     * Returns country names as an associative array with their dial codes as the key.
     *
     * @return  array   Format: `['dialCode' => 'name', 'dialCode' => 'name', ...]`.
     */
    public static function getCountryNamesByDialCode()
    {
        return self::getCountries('name', 'dialCode');
    }

    /**
     * Returns country names with their native name and dial codes as an associative array with their dial codes as the key.
     *
     * @return  array   Format: `['dialCode' => 'name (nativeName) (dialCode)', 'dialCode' => 'name (nativeName) (dialCode)', ...]`.
     */
    public static function getCountryFullInfoByDialCode()
    {
        return self::getCountries('fullInfo', 'dialCode');
    }

    /**
     * Returns countries' all dial codes as an associative array with their codes as the key.
     *
     * @return  array   Format: `['code' => 'allDialCodes', 'code' => 'allDialCodes', ...]`.
     */
    public static function getAllDialCodesByCode()
    {
        return self::getCountries('allDialCodes', 'code');
    }
}
