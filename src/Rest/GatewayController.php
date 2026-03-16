<?php

namespace WSms\Rest;

use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\Message\EmailMessage;
use WSms\Messaging\Message\SmsMessage;
use WSms\Messaging\Message\WebhookMessage;

defined('ABSPATH') || exit;

class GatewayController
{
    private const NAMESPACE = 'wsms/v1';

    public function __construct(
        private readonly GatewayRegistry $gatewayRegistry,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/gateways', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/config', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getConfig'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'updateConfig'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/test', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'testSend'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'channel' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'to'      => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'body'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
                ],
            ],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(): \WP_REST_Response
    {
        $gateways = [];
        $configs = get_option('wsms_gateway_configs', []);

        foreach ($this->gatewayRegistry->all() as $id => $gateway) {
            $gateways[] = [
                'id'                 => $id,
                'name'               => $gateway->getName(),
                'supported_channels' => $gateway->getSupportedChannels(),
                'config_schema'      => $gateway->getConfigSchema(),
                'is_configured'      => $gateway->isConfigured(),
                'config'             => $configs[$id] ?? [],
            ];
        }

        return new \WP_REST_Response([
            'items' => $gateways,
            'total' => count($gateways),
        ]);
    }

    public function getConfig(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'data'    => get_option('wsms_gateway_configs', []),
        ]);
    }

    public function updateConfig(\WP_REST_Request $request): \WP_REST_Response
    {
        $config = $request->get_params();
        update_option('wsms_gateway_configs', $config);

        return new \WP_REST_Response(['success' => true]);
    }

    public function testSend(\WP_REST_Request $request): \WP_REST_Response
    {
        $gateway = $this->gatewayRegistry->get($request->get_param('id'));

        if (!$gateway) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Gateway not found', 'wp-sms'),
            ], 404);
        }

        $channel = $request->get_param('channel') ?? $gateway->getSupportedChannels()[0] ?? 'sms';
        $to = $request->get_param('to') ?? '';
        $body = $request->get_param('body') ?? __('Test message from WSMS', 'wp-sms');

        $message = match ($channel) {
            'email'   => new EmailMessage($to, $body, __('WSMS Test', 'wp-sms')),
            'webhook' => new WebhookMessage($to, $body),
            default   => new SmsMessage($to, $body),
        };

        $result = $gateway->send($message);

        return new \WP_REST_Response([
            'success'     => $result->success,
            'data'        => [
                'status'      => $result->status,
                'provider_id' => $result->providerId,
                'error'       => $result->error,
            ],
        ]);
    }
}
