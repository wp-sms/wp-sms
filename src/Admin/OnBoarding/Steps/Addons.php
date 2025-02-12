<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;

class Addons extends StepAbstract
{
    protected function initialize()
    {
        // TODO: Implement initialize() method.
    }

    public function getSlug()
    {
        return "addons";
    }

    protected function getTitle()
    {
        return __('Addons', 'wp-sms');
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