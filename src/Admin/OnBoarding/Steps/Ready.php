<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;

if (!defined('ABSPATH')) exit;

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

    public function getTitle()
    {
        return __('Ready', 'wp-sms');
    }

    protected function getDescription()
    {
        // TODO: Implement getDescription() method.
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