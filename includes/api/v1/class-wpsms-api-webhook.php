<?php

namespace WP_SMS\Api\V1;

use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_SMS\RestApi;
use WP_SMS\Webhook\WebhookFactory;

/**
 * Class Webhook
 */
class Webhook extends RestApi
{
    public function __construct()
    {
        // Register routes
        add_action('rest_api_init', array($this, 'register_routes'));

        parent::__construct();
    }

    /**
     * Register routes
     */
    public function register_routes()
    {
        register_rest_route($this->namespace . '/v1', '/webhook', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'subscribeWebhookCallback'),
                'args'                => array(
                    'webhook_url' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => array($this, 'validateWebhookUrlCallback'),
                    ),
                    'type'        => array(
                        'required' => true,
                        'enum'     => ['new_sms', 'new_subscriber'],
                        'type'     => 'string'
                    ),
                ),
                'permission_callback' => array($this, 'subscribeWebhookPermission'),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'unsubscribeWebhookCallback'),
                'args'                => array(
                    'webhook_url' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => array($this, 'validateWebhookUrlCallback'),
                    ),
                    'type'        => array(
                        'required' => true,
                        'enum'     => ['new_sms', 'new_subscriber'],
                        'type'     => 'string'
                    ),
                ),
                'permission_callback' => array($this, 'subscribeWebhookPermission'),
            )
        ));
    }

    /**
     * Subscribe webhook callback
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function subscribeWebhookCallback(WP_REST_Request $request)
    {
        try {

            $webhookUrl  = $request->get_param('webhook_url');
            $webhookType = $request->get_param('type');

            WebhookFactory::subscribeWebhook($webhookUrl, $webhookType);

            return self::response('Webhook subscribed successfully');

        } catch (Exception $e) {
            return self::response($e->getMessage(), 400);
        }
    }

    /**
     * Unsubscribe webhook callback
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     *
     */
    public function unsubscribeWebhookCallback(WP_REST_Request $request)
    {
        try {

            $webhookUrl  = $request->get_param('webhook_url');
            $webhookType = $request->get_param('type');

            WebhookFactory::unsubscribeWebhook($webhookUrl, $webhookType);

            return self::response('Webhook unsubscribed successfully');

        } catch (Exception $e) {
            return self::response($e->getMessage(), 400);
        }
    }

    /**
     * Check user permission
     *
     * @param $request
     *
     * @return bool
     */
    public function subscribeWebhookPermission($request)
    {
        return current_user_can('wpsms_setting');
    }

    /**
     * @param $param
     * @return false|mixed
     */
    public function validateWebhookUrlCallback($param)
    {
        $parsedWebhookUrl = parse_url($param);

        // Just to be safe
        if ($parsedWebhookUrl['scheme'] != 'https') {
            return false;
        }

        return filter_var($param, FILTER_VALIDATE_URL);
    }
}

new Webhook();
