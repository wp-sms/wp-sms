<?php

namespace WP_SMS\Admin\OnBoarding\Steps;

use Exception;
use WP_SMS\Admin\OnBoarding\StepAbstract;
use WP_SMS\Components\RemoteRequest;
use WP_SMS\Option;

class SmsGateway extends StepAbstract
{
    const CACHE_KEY      = 'wp_sms_gateways';
    const CACHE_DURATION = 43200; // 12 hours in seconds

    public function getFields()
    {
        return ['name'];
    }

    protected function initialize()
    {
        $this->setData('gateways', $this->getAllPages());
    }

    private function getAllPages()
    {
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false) {
            return $cached;
        }

        $gateways = array();
        $page     = 1;

        try {
            do {
                $request  = new RemoteRequest('get', "https://staging.wp-sms-pro.com/wp-json/wp/v2/gateway?per_page=100&page={$page}");
                $response = $request->execute();

                if (is_array($response) && !empty($response)) {
                    $gateways = array_merge($gateways, $response);
                    $page++;
                } else {
                    break;
                }
            } while (count($response) === 100);

            // Cache the data
            set_transient(self::CACHE_KEY, $gateways, self::CACHE_DURATION);
        } catch (Exception $e) {
            error_log('Error fetching pages: ' . $e->getMessage());
        }

        return $gateways;
    }

    public function getSlug()
    {
        return "sms-gateway";
    }

    protected function getTitle()
    {
        return __('SMS Gateway', 'wp-sms');
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
            'name' => 'required',
        ];
    }

    public function afterValidation()
    {
        Option::updateOption('gateway_name', $this->data['name']);

    }

}