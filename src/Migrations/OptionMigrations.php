<?php

namespace WP_SMS\Migrations;

use WP_SMS\Option;

class OptionMigrations
{
    public function register()
    {
        // Migrate deprecated settings if international mode is off
        $this->migrateLegacyCountryCodeSetting();

        // Register filter for backward compatibility
        add_filter('wp_sms_get_option_value', [$this, 'filterDeprecatedMobileCountyCode'], 10, 3);
        add_filter('wp_sms_get_option_value', [$this, 'filterDeprecatedInternationalMobileCheckbox'], 10, 3);
    }

    /**
     * Migrate legacy `mobile_county_code` to `international_mobile_only_countries`
     * if international mode was previously disabled.
     */
    private function migrateLegacyCountryCodeSetting()
    {
        $isIntlEnabled = Option::getOption('international_mobile');

        // Only migrate if international mode was off
        if ($isIntlEnabled) {
            return;
        }

        $legacyDialCode = Option::getOption('mobile_county_code');
        if (empty($legacyDialCode)) {
            return;
        }

        $allDialCodes = wp_sms_countries()->getAllDialCodesByCode();
        foreach ($allDialCodes as $isoCode => $dialCodes) {
            if (in_array($legacyDialCode, $dialCodes, true)) {
                Option::updateOption('international_mobile_only_countries', [$isoCode]);
                break;
            }
        }

        // Clean up deprecated options
        Option::deleteOption('mobile_county_code');
        Option::deleteOption('mobile_terms_minimum');
        Option::deleteOption('mobile_terms_maximum');
        Option::deleteOption('international_mobile');
    }

    /**
     * Handle deprecated 'mobile_county_code' by mapping it to the first allowed country dial code.
     * Keeps legacy compatibility while showing a deprecation warning.
     *
     * @param mixed $value The original option value.
     * @param string $key The option key.
     * @param bool $pro Whether this is a pro option context.
     * @return mixed
     */
    public function filterDeprecatedMobileCountyCode($value, $key, $pro)
    {
        if ($key !== 'mobile_county_code') {
            return $value;
        }

        _deprecated_argument(
            'wp_sms_get_option_value',
            '7.0.1',
            __('The "mobile_county_code" option is deprecated. Please use "international_mobile_only_countries" instead.', 'wp-sms')
        );

        $onlyCountries = Option::getOption('international_mobile_only_countries');

        if (is_array($onlyCountries) && !empty($onlyCountries)) {
            $firstCountryCode = $onlyCountries[0];
            $dialCode = wp_sms_countries()->getDialCodeByCountryCode($firstCountryCode);
            return $dialCode !== null ? $dialCode : '0';
        }

        return '0';
    }

    /**
     * Handle deprecated 'international_mobile' returning true
     *
     * @param mixed $value The original option value.
     * @param string $key The option key.
     * @param bool $pro Whether this is a pro option context.
     * @return mixed
     */
    public function filterDeprecatedInternationalMobileCheckbox($value, $key, $pro)
    {
        if ($key !== 'international_mobile') {
            return $value;
        }

        _deprecated_argument(
            'wp_sms_get_option_value',
            '7.0.1',
            __('The "international_mobile" option is deprecated and is always true.', 'wp-sms')
        );


        return true;
    }

}
