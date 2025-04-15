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
        // TODO: Implement initialize() method.
        wp_enqueue_style('wpsms-intel-tel-input', WP_SMS_URL . 'assets/css/intlTelInput.min.css', true, '24.5.0');
        wp_enqueue_script('wpsms-intel-tel-input', WP_SMS_URL . 'assets/js/intel/intlTelInput.min.js', array('jquery'), '24.5.0', true);
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
        $tel          = is_array($this->data['tel']) ? $this->data['tel'][0] : $this->data['tel'];
        $country_code = $this->data['code'] ?: '';
        Option::updateOption('admin_mobile_number', $tel);
        Option::updateOption('admin_mobile_number_country_prefix', $country_code);
    }
}