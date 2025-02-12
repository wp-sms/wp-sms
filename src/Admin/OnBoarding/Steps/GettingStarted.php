<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;

class GettingStarted extends StepAbstract
{
    /**
     * @return array
     */
    public function getFields(): array
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
}