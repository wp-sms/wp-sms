<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;

class SmsGateway extends StepAbstract
{
    /**
     * @return array
     */
    public function getFields(): array
    {
        return ['name'];
    }

    protected function initialize()
    {
        // TODO: Implement initialize() method.
    }

    public function getSlug()
    {
        return "sms-gateway";
    }

    protected function getTitle()
    {
        return __('SMS Gateway', 'wp-sms');
    }

    protected function getDescription()
    {
        // TODO: Implement getDescription() method.
    }

    public function completeIf()
    {
        // TODO: Implement completeIf() method.
    }
}