<?php

namespace WP_SMS\Webhook;

use Exception;
use WP_Error;

abstract class WebhookAbstract
{
    protected $webhookType;
    protected $webhookAction;

    /**
     * @return void|WP_Error
     */
    public static function boot()
    {
        try {

            $class  = self::getClassName();
            $action = new $class;

            $action->init();

        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    public function init()
    {
        add_action($this->webhookAction['actionName'], [$this, 'run'], 10, $this->webhookAction['acceptArgs']);
    }

    public static function getClassName()
    {
        return get_called_class();
    }

    protected function fetchWebhooks()
    {
        return WebhookFactory::getWebhooks($this->webhookType);
    }

    /**
     * @param $url
     * @param $data
     * @return void|WP_Error
     * @throws Exception
     */
    protected function execute($url, $data)
    {
        try {

            $params = array(
                'method'  => 'POST',
                'body'    => wp_json_encode($data),
                'headers' => array(
                    'Content-Type'           => 'application/json',
                    'X-WP-SMS-Webhook-Event' => $this->webhookType,
                    'X-WP-SMS-Version'       => WP_SMS_VERSION,
                )
            );

            $response = wp_safe_remote_request(trim($url), $params);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $responseCode = wp_remote_retrieve_response_code($response);
            $responseBody = wp_remote_retrieve_body($response);

            if (in_array($responseCode, [200, 201, 202]) === false) {
                // translators: %s: Error message
                throw new Exception(sprintf(esc_html__('Failed to get success response, %s', 'wp-sms'), print_r($responseBody, 1)));
            }

        } catch (\Throwable $e) {
            error_log(sprintf('WP SMS: The provided webhook could not be executed, Error Message: %s', $e->getMessage()));
        }
    }
}