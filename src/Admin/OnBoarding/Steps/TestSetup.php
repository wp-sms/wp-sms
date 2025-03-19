<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;
use WP_SMS\Components\Sms;
use WP_SMS\Option;
use WP_SMS\Utils\Request;

class TestSetup extends StepAbstract
{
    private $sms;

    protected function initialize()
    {
        global $sms;
        $this->sms = $sms;
        $credit    = $this->sms->GetCredit();
        $is_active = !is_wp_error($credit) && $credit !== false;

        $this->setData('gateway_status', $is_active);
    }

    public function getSlug()
    {
        return 'test-setup';
    }

    public function getTitle()
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

    public function getCTAs()
    {
        return [
            'received'     => [
                'text' => __('Yes, I received it!', 'wp-sms'),
            ],
            'not_received' => [
                'text' => __('No, I didn\'t receive it.', 'wp-sms'),
                'url'  => '#'
            ]
        ];
    }
}