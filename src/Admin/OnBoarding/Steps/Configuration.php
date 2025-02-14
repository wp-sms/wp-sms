<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;

class Configuration extends StepAbstract
{
    /**
     * @return array
     */
    public function getFields(): array
    {
        return ['username', 'password', 'tel'];
    }

    protected function initialize()
    {
    }

    public function getSlug()
    {
        return 'configuration';
    }

    protected function getTitle()
    {
        return __('Configuration', 'wp-sms');
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
        return [
            'tel'      => 'required|numeric',
            'username' => 'required',
            'password' => 'required',
        ];
    }
}