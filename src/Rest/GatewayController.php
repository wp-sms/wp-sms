<?php

namespace WSms\Rest;

use WSms\Log\Contracts\MessageLoggerInterface;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\Message\EmailMessage;
use WSms\Messaging\Message\Message;
use WSms\Messaging\Message\WebhookMessage;

defined('ABSPATH') || exit;

class GatewayController extends Controller
{
    public function __construct(
        private readonly GatewayRegistry $gatewayRegistry,
        private readonly MessageLoggerInterface $messageLogger,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/gateways', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'channel' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'region'  => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'search'  => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
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

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/test-connection', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'testConnection'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/credit', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getCredit'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/gateways/(?P<id>[a-z_]+)/config-options/(?P<section>[a-z_]+)/(?P<field>[a-z_]+)', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'configOptions'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $filterChannel = $request->get_param('channel');
        $filterRegion = $request->get_param('region');
        $filterSearch = $request->get_param('search');

        $gateways = [];
        $configs = get_option('wsms_gateway_configs', []);

        foreach ($this->gatewayRegistry->all() as $id => $gateway) {
            if ($filterChannel && !in_array($filterChannel, $gateway->getSupportedChannels())) {
                continue;
            }

            $metadata = $gateway->getMetadata();

            if ($filterRegion) {
                $regions = $metadata['regions'] ?? [];
                if (!empty($regions) && !in_array($filterRegion, $regions) && !in_array('global', $regions)) {
                    continue;
                }
            }

            if ($filterSearch) {
                $haystack = strtolower($gateway->getName() . ' ' . ($metadata['description'] ?? ''));
                if (!str_contains($haystack, strtolower($filterSearch))) {
                    continue;
                }
            }

            $gateways[] = [
                'id'                 => $id,
                'name'               => $gateway->getName(),
                'supported_channels' => $gateway->getSupportedChannels(),
                'config_schema'      => $gateway->getConfigSchema(),
                'is_configured'      => $gateway->isConfigured(),
                'config'             => $configs[$id] ?? [],
                'metadata'           => $metadata,
                'features'           => $gateway->getFeatures(),
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
        $config = $request->get_json_params();

        update_option('wsms_gateway_configs', $config);

        return new \WP_REST_Response(['success' => true]);
    }

    public function testSend(\WP_REST_Request $request): \WP_REST_Response
    {
        $gateway = $this->resolveGateway($request);
        if ($gateway instanceof \WP_REST_Response) {
            return $gateway;
        }

        $channel = $request->get_param('channel') ?? $gateway->getSupportedChannels()[0] ?? 'sms';
        $to = $request->get_param('to') ?? '';
        $body = $request->get_param('body') ?? __('Test message from WSMS', 'wp-sms');

        $message = match ($channel) {
            'email'   => new EmailMessage($to, $body, __('WSMS Test', 'wp-sms')),
            'webhook' => new WebhookMessage($to, $body),
            default   => new Message($channel, $to, $body),
        };

        $result = $gateway->send($message);

        try {
            $this->messageLogger->logSend(
                gatewayId:  $request->get_param('id'),
                channel:    $channel,
                recipient:  $to,
                body:       $body,
                status:     $result->success ? $result->status : 'failed',
                subject:    $message instanceof EmailMessage ? $message->subject : null,
                providerId: $result->providerId,
                error:      $result->error,
                cost:       $result->cost,
                type:       'test',
            );
        } catch (\Throwable $e) {
            // Logging is best-effort — don't fail the test-send response.
        }

        return new \WP_REST_Response([
            'success'     => $result->success,
            'data'        => [
                'status'      => $result->status,
                'provider_id' => $result->providerId,
                'error'       => $result->error,
            ],
        ]);
    }

    public function testConnection(\WP_REST_Request $request): \WP_REST_Response
    {
        $gateway = $this->resolveGateway($request);
        if ($gateway instanceof \WP_REST_Response) {
            return $gateway;
        }

        $result = $gateway->testConnection();

        return new \WP_REST_Response([
            'success' => $result->success,
            'message' => $result->message,
            'details' => $result->details,
        ]);
    }

    public function getCredit(\WP_REST_Request $request): \WP_REST_Response
    {
        $gateway = $this->resolveGateway($request);
        if ($gateway instanceof \WP_REST_Response) {
            return $gateway;
        }

        $credit = $gateway->getCredit();

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'credit' => $credit,
            ],
        ]);
    }

    public function configOptions(\WP_REST_Request $request): \WP_REST_Response
    {
        $gateway = $this->resolveGateway($request);
        if ($gateway instanceof \WP_REST_Response) {
            return $gateway;
        }

        if (!$gateway instanceof SupportsDynamicOptions) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_supported',
                'message' => __('This gateway does not support dynamic options', 'wp-sms'),
            ], 400);
        }

        $section = $request->get_param('section');
        $field = $request->get_param('field');
        $body = $request->get_json_params();

        // Use draft config from request body, fall back to saved config
        $config = $body['config'] ?? get_option('wsms_gateway_configs', [])[$gateway->getId()] ?? [];
        $context = $body['context'] ?? [];

        // Verify the field exists and is marked as dynamic
        $schema = $gateway->getConfigSchema();
        $fieldSchema = $section === 'shared'
            ? ($schema['shared'][$field] ?? null)
            : ($schema['channels'][$section][$field] ?? null);

        if (!$fieldSchema || empty($fieldSchema['dynamic'])) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_dynamic',
                'message' => __('This field does not support dynamic options', 'wp-sms'),
            ], 400);
        }

        if (!$gateway->validateConfig($config)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'credentials_missing',
                'message' => __('Required credentials are missing', 'wp-sms'),
            ], 422);
        }

        try {
            $options = $gateway->getConfigOptions($field, $section, $config, $context);

            return new \WP_REST_Response([
                'options' => $options,
            ]);
        } catch (\RuntimeException $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'provider_error',
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    private function resolveGateway(\WP_REST_Request $request): \WSms\Messaging\Contracts\GatewayInterface|\WP_REST_Response
    {
        $gateway = $this->gatewayRegistry->get($request->get_param('id'));

        if (!$gateway) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Gateway not found', 'wp-sms'),
            ], 404);
        }

        return $gateway;
    }
}
