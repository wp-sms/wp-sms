<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;
use WP_SMS\Option;

class GettingStarted extends StepAbstract
{
    const COUNTRY_DEFAULT_VALUE = 'global';

    public function getFields()
    {
        return ['tel', 'countries', 'code'];
    }

    protected function initialize()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueIntelAssets']);
    }


    public function enqueueIntelAssets()
    {
        wp_enqueue_style(
            'wp-sms-intel-style',
            WP_SMS_URL . 'assets/css/intlTelInput.min.css',
            [],
            '24.5.0'
        );

        wp_enqueue_script(
            'wp-sms-intel-script',
            WP_SMS_URL . 'assets/js/intel/intlTelInput.min.js',
            ['jquery'],
            '24.5.0',
            true
        );

        wp_localize_script(
            'wp-sms-intel-script',
            'wp_sms_intel_tel_util',
            [
                'util_js' => WP_SMS_URL . 'assets/js/intel/utils.js'
            ]
        );
    }


    public function getSlug()
    {
        return "getting-started";
    }

    public function getTitle()
    {
        return __('Getting Started', 'wp-sms');
    }

    protected function getDescription()
    {
        // TODO: Implement getDescription() method.
    }

    protected function validationRules()
    {
        return [
            'tel' => 'required',
        ];
    }

    public function afterValidation()
    {
        $raw_phone        = is_array($this->data['tel']) ? $this->data['tel'][0] : $this->data['tel'];
        $raw_country_code = $this->data['code'] ?: '';

        // Keep leading +, remove all other non-digit characters
        $normalized_phone        = preg_replace('/(?!^\+)\D+/', '', $raw_phone);
        $normalized_country_code = preg_replace('/\D+/', '', $raw_country_code);

        if ($normalized_country_code && strpos($normalized_phone, '+' . $normalized_country_code) !== 0) {
            $normalized_phone = '+' . $normalized_country_code . ltrim($normalized_phone, '+');
        }

        Option::updateOption('admin_mobile_number', $normalized_phone);
        Option::updateOption('admin_mobile_number_country_prefix', '+' . $normalized_country_code);
    }
}