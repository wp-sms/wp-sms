<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;
use WP_SMS\Gateway;
use WP_SMS\Option;

class Configuration extends StepAbstract
{
    protected $sms;

    public function getFields()
    {
        $ids = [];
        if (is_array($this->data && $this->data['fields'])) {
            foreach ($this->data['fields'] as $item) {
                $ids[] = $item['id'];
            }
        }
        return $ids;
    }

    protected function initialize()
    {
        $this->setGatewayFields();
        add_action('onboarding_before_test_gateway_response', array($this, 'afterValidation'));
    }

    protected function setGatewayFields()
    {
        @\WP_SMS::get_instance()->init();
        global $sms;
        $this->sms = $sms;
        $this->setData('fields', $sms->gatewayFields);
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

    protected function validationRules()
    {
        $validationArray = [];
        foreach ($this->getFields() as $field) {
            $validationArray[$field] = 'required';
        }

        return $validationArray;
    }

    public function getCTAs()
    {
        return [
            'test' => [
                'text' => __('Test Gateway', 'wp-sms'),
                'id'   => 'wp_sms_test_connection'
            ]
        ];
    }

    public function afterValidation()
    {
        foreach ($this->getFields() as $field) {
            Option::updateOption($field, $this->data[$field]);
        }
    }

}