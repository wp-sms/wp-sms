<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use Exception;
use WP_SMS\Admin\OnBoarding\StepAbstract;
use WP_SMS\Components\RemoteRequest;
use WP_SMS\Option;
use WP_SMS\Utils\Request;

class SmsGateway extends StepAbstract
{
    const CACHE_DURATION = 43200; // 12 hours in seconds

    public function getFields()
    {
        return ['name'];
    }

    protected function initialize()
    {
        if (Request::get('step') == $this->getSlug()) {
            $this->setData('gateways', $this->getAllPages());
        }
    }

    private function getAllPages()
    {
        $gateways = array();
        $page     = 1;

        try {
            do {
                $request = new RemoteRequest('get', "https://wp-sms-pro.com/wp-json/wp/v2/gateway?per_page=100&page={$page}");

                $response = $request->execute(true, true, self::CACHE_DURATION);


                if (is_array($response) && !empty($response)) {
                    $gateways = array_merge($gateways, $response);
                    $page++;
                } else {
                    break;
                }
            } while (count($response) === 100);
        } catch (Exception $e) {
            error_log(sprintf(__('Error fetching pages: %s', 'wp-sms'), $e->getMessage()));
        }

        return $gateways;
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

        $country_code = \WP_SMS\Option::getOption('mobile_county_code');

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