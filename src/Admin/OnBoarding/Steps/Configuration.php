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
        foreach ($this->data['fields'] as $item) {
            $ids[] = $item['id'];
        }
        return $ids;
    }

    protected function initialize()
    {
        $this->setGatewayFields();
        $this->registerAjaxAction();
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

    public function completeIf()
    {
        // TODO: Implement completeIf() method.
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

    public function registerAjaxAction()
    {
        add_action('wp_ajax_test_connection', [$this, 'ajaxHandler']);
    }

    public function ajaxHandler()
    {
        check_ajax_referer('wp_sms_wizard_nonce', 'nonce');

        $this->afterValidation();

        $response = [
            'status'   => !is_wp_error($this->sms->GetCredit) ? 'active' : 'deactive',
            'balance'  => $this->sms->GetCredit ? $this->sms->GetCredit : 0,
            'incoming' => $this->sms->supportIncoming ? 'true' : 'false',
            'bulk'     => $this->sms->bulk_send ? 'true' : 'false',
            'mms'      => $this->sms->supportMedia ? 'true' : 'false',
        ];

        wp_send_json_success($response, 200);

    }
}