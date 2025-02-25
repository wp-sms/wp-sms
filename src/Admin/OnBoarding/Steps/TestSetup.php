<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;
use WP_SMS\Components\Sms;
use WP_SMS\Option;

class TestSetup extends StepAbstract
{
    protected function initialize()
    {
        $params = [
            'to'  => Option::getOption('admin_mobile_number'),
            'msg' => __('This is a test from WP-SMS onboarding process.')
        ];

        if (Sms::send($params)) {
            $this->markAsInitialized();
        }
    }

    public function getSlug()
    {
        return 'test-setup';
    }

    protected function getTitle()
    {
        return __('Test Your Setup', 'wp-sms');
    }

    protected function getDescription()
    {
        // TODO: Implement getDescription() method.
    }


    protected function validationRules()
    {
        // TODO: Implement validationRules() method.
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return [];
    }
}