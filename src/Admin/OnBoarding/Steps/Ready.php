<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;

class Ready extends StepAbstract
{
    protected function initialize()
    {
        // TODO: Implement initialize() method.
    }

    public function getSlug()
    {
        return 'ready';
    }

    protected function getTitle()
    {
        return __('Ready', 'wp-sms');
    }

    protected function getDescription()
    {
        // TODO: Implement getDescription() method.
    }

    public function completeIf()
    {
        // TODO: Implement completeIf() method.
    }

    protected function validationRules()
    {
        // TODO: Implement validationRules() method.
    }

    protected function getFields()
    {
        return [];
    }
}