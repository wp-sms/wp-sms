<?php

namespace WP_SMS\Components;

class Countries extends Singleton
{
    private $countries = [];

    /**
     * Array of countries, where all items with similar dial codes are merged together.
     *
     * @var array   Format: `['dialCode' => 'fullInfo', ...]`.
     */
    private $countriesMergedBySimilarDialCodes = [];

    private $countriesFileDir = WP_SMS_DIR . 'assets/countries.json';

    public function __construct()
    {
        // Already initialized
        if (!empty($this->countries)) return;

        $this->countries = $this->getCountriesFile();

        $this->addFullInfoField();
    }

    /**
     * Tries to get the content of the `countries.json` file as an associative array.
     *
     * @return  array   Countries list as an associative array, or empty array on error.
     */
    private function getCountriesFile()
    {
        $jsonData = wp_json_file_decode($this->countriesFileDir, ['associative' => true]);
        return $jsonData !== null ? $jsonData : [];
    }

    /**
     * Adds the `fullInfo` field to the countries array.
     *
     * Country name + country native name + dial code will be displayed in this field.
     *
     * @return  void
     */
    private function addFullInfoField()
    {
        if (empty($this->countries)) return;

        foreach ($this->countries as $index => $country) {
            if (!empty($country['fullInfo'])) continue;

            $fullInfo = $country['name'];
            if ($country['name'] !== $country['nativeName'])
                $fullInfo .= " ({$country['nativeName']})";
            $fullInfo .= ' (' . implode($country['allDialCodes']) . ')';

            $this->countries[$index]['fullInfo'] = $fullInfo;
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
    public function getCountries($field = '', $key = '')
    {
        if (empty($this->countries))
            return [];

        if (!empty($field) && !empty($key))
            return wp_list_pluck($this->countries, $field, $key);

        return $this->countries;
    }

    /**
     * Initializes and returns the `$countriesMergedBySimilarDialCodes` array (which merges all countries with similar dial codes).
     *
     * @return  array   Format: `['dialCode' => 'fullInfo', ...]`.
     */
    public function getCountriesMerged()
    {
        if (!empty($this->countriesMergedBySimilarDialCodes)) return $this->countriesMergedBySimilarDialCodes;

        foreach ($this->countries as $country) {
            if (empty($country['fullInfo'])) continue;

            // Add the country if its dialCode not exists in the array:
            if (!array_key_exists($country['dialCode'], $this->countriesMergedBySimilarDialCodes)) {
                $this->countriesMergedBySimilarDialCodes[$country['dialCode']] = $country['fullInfo'];
                continue;
            }
            // Else, another country with a similar dialCode exists

            $newInfoToAppend = $country['name'];
            if ($country['name'] !== $country['nativeName'])
                $newInfoToAppend .= " ({$country['nativeName']})";

            // Add new country's name and nativeName before the ending parentheses:
            $this->countriesMergedBySimilarDialCodes[$country['dialCode']] = str_replace('(+', "& $newInfoToAppend (+", $this->countriesMergedBySimilarDialCodes[$country['dialCode']]);
        }

        return $this->countriesMergedBySimilarDialCodes;
    }

    /**
     * Returns country names as an associative array with their dial codes as the key.
     *
     * @return  array   Format: `['dialCode' => 'name', 'dialCode' => 'name', ...]`.
     */
    public function getCountryNamesByDialCode()
    {
        return $this->getCountries('name', 'dialCode');
    }

    /**
     * Returns country names with their native name and dial codes as an associative array with their dial codes as the key.
     *
     * @return  array   Format: `['dialCode' => 'name (nativeName) (dialCode)', 'dialCode' => 'name (nativeName) (dialCode)', ...]`.
     */
    public function getCountryFullInfoByDialCode()
    {
        return $this->getCountries('fullInfo', 'dialCode');
    }

    /**
     * Returns countries' all dial codes as an associative array with their codes as the key.
     *
     * @return  array   Format: `['code' => 'allDialCodes', 'code' => 'allDialCodes', ...]`.
     */
    public function getAllDialCodesByCode()
    {
        return $this->getCountries('allDialCodes', 'code');
    }
}
