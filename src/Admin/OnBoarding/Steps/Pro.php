<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;

if (!defined('ABSPATH')) exit;

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

    public function getTitle()
    {
        return __('WP SMS All-in-One', 'wp-sms');
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