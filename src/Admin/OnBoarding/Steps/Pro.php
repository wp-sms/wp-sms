<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;

class Pro extends StepAbstract
{
    protected function initialize()
    {
        // TODO: Implement initialize() method.
    }

    public function getSlug()
    {
        return "update-all-in-one";
    }

    protected function getTitle()
    {
        return __('Update to All-In-One', 'wp-sms');
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