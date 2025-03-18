<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;
use WP_SMS\Option;

class GettingStarted extends StepAbstract
{
    const COUNTRY_DEFAULT_VALUE = 'global';

    public function getFields()
    {
        return ['tel', 'countries'];
    }

    protected function initialize()
    {
        // TODO: Implement initialize() method.
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
            'tel'       => 'required|numeric',
            'countries' => 'required|text'
        ];
    }

    public function afterValidation()
    {
        Option::updateOption('admin_mobile_number', $this->data['tel']);
        Option::updateOption('mobile_county_code', $this->data['countries']);
    }
}