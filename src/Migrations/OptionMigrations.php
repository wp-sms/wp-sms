<?php

namespace WP_SMS\Migrations;

use WP_SMS\Option;

class OptionMigrations
{
    public function register()
    {
        // Filter WP SMS options and override legacy keys like `mobile_county_code`
        add_filter('wp_sms_get_option_value', [$this, 'filterDeprecatedMobileCountryCode'], 10, 3);
    }

    /**
     * Handle deprecated 'mobile_county_code' by mapping it to the first allowed country dial code.
     *
     * @param mixed $value The original option value.
     * @param string $key The option key.
     * @param bool $pro Whether this is a pro option context.
     * @return mixed        The filtered (or original) value.
     */
    public function filterDeprecatedMobileCountryCode($value, $key, $pro)
    {
        if ($key !== 'mobile_county_code') {
            return $value;
        }

        // Trigger deprecation warning
        _deprecated_argument(
            'wp_sms_get_option_value',
            '6.9.15',
            __('The "mobile_county_code" option is deprecated. Please use "international_mobile_only_countries" instead.', 'wp-sms')
        );

        $onlyCountries = Option::getOption('international_mobile_only_countries');

        if (is_array($onlyCountries) && !empty($onlyCountries)) {
            $firstCountryCode = $onlyCountries[0];
            $dialCode         = wp_sms_countries()->getDialCodeByCountryCode($firstCountryCode);
            return $dialCode !== null ? $dialCode : '0';
        }

        return '0';
    }


}