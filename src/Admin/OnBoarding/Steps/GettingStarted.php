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

    protected function getTitle()
    {
        return __('Getting Started', 'wp-sms');
    }

    protected function getDescription()
    {
        // TODO: Implement getDescription() method.
    }

    public function completeIf()
    {
        return true;
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
        Option::updateOption('admin_mobile_number', $this->data['countries'] == self::COUNTRY_DEFAULT_VALUE ? $this->data['countries'] . $this->data['tel'] : $this->data['tel']);
    }
}