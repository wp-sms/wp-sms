<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;

class TestSetup extends StepAbstract
{
    protected function initialize()
    {
        // TODO: Implement initialize() method.
    }

    public function getSlug()
    {
        return 'test-setup';
    }

    protected function getTitle()
    {
        return __('Test Setup', 'wp-sms');
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

    protected function getField()
    {
        // TODO: Implement getField() method.
    }

    /**
     * @return array
     */
    public function getFields(): array
    {
        return [];
    }
}