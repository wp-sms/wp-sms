<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use WP_SMS\Admin\OnBoarding\StepAbstract;
use WP_SMS\Option;
use WP_SMS\Services\Gateway\GatewayRegistry;

if (!defined('ABSPATH')) exit;

class SmsGateway extends StepAbstract
{
    public function getFields()
    {
        return ['name'];
    }

    protected function initialize()
    {
        if (\WP_SMS\Utils\Request::get('step') == $this->getSlug()) {
            $registry = GatewayRegistry::getGateways();
            $this->setData('gateways', $registry['gateways'] ?? []);
        }
    }

    public function getSlug()
    {
        return "sms-gateway";
    }

    public function getTitle()
    {
        return __('SMS Gateway', 'wp-sms');
    }

    public function extraData()
    {
        $result = ['country' => ''];

        $country_code = Option::getOption('mobile_county_code');

        if (empty($country_code)) {
            return $result;
        }

        $country_obj = new \WP_SMS\Components\Countries();

        $country = $country_obj->getCountryByPrefix($country_code);

        if (!empty($country) && isset($country['name'])) {
            $result['country'] = $country['name'];
        }

        return $result;
    }

    protected function getDescription()
    {
        // TODO: Implement getDescription() method.
    }

    protected function validationRules()
    {
        return [
            'name' => 'required',
        ];
    }

    public function afterValidation()
    {
        Option::updateOption('gateway_name', $this->data['name']);
    }
}
