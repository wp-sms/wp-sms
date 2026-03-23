<?php

namespace WSms\Rest;

use WSms\Flow\Action\ActionRegistry;
use WSms\Flow\Trigger\TriggerRegistry;
use WSms\Integration\IntegrationRegistry;
use WSms\Integration\Contracts\IntegrationCapability;
use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\Contracts\SupportsContactSync;
use WSms\Integration\Contracts\SupportsListManagement;
use WSms\Integration\Contracts\SupportsSuppressionSync;
use WSms\Integration\Marketing\SuppressionPoller;
use WSms\Integration\Webhook\WebhookIntegration;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\MarketingPushContactJob;

defined('ABSPATH') || exit;

class IntegrationController extends Controller
{
    private const CONFIG_OPTION = 'wsms_integration_configs';
    private const SYNC_STATE_OPTION = 'wsms_marketing_sync_state';

    public function __construct(
        private readonly IntegrationRegistry $integrationRegistry,
        private readonly TriggerRegistry $triggerRegistry,
        private readonly ActionRegistry $actionRegistry,
        private readonly ?QueueInterface $queue = null,
        private readonly ?SuppressionPoller $suppressionPoller = null,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/integrations', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/config', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'saveConfig'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'deleteConfig'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/triggers', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'triggers'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/triggers/(?P<triggerId>[\\w.]+)/filter-options/(?P<field>[\\w]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'triggerFilterOptions'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/actions', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'actions'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/actions/(?P<actionId>[\\w_]+)/config-options/(?P<field>[\\w]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'actionConfigOptions'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/sync-settings', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'saveSyncSettings'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/sync', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'triggerSync'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/poll', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'triggerPoll'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/lists', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'integrationLists'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/webhook/endpoints', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'listWebhookEndpoints'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'createWebhookEndpoint'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/webhook/endpoints/(?P<endpointId>[A-Za-z0-9]+)', [
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'deleteWebhookEndpoint'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function index(): \WP_REST_Response
    {
        $configs = get_option(self::CONFIG_OPTION, []);
        $syncState = get_option(self::SYNC_STATE_OPTION, []);
        $integrations = [];

        foreach ($this->integrationRegistry->getAll() as $integration) {
            $integrations[] = $this->formatIntegrationSummary($integration, $configs, $syncState);
        }

        return new \WP_REST_Response([
            'items' => $integrations,
            'total' => count($integrations),
        ]);
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $integration = $this->resolveIntegration($request);
        if ($integration instanceof \WP_REST_Response) {
            return $integration;
        }

        $configs = get_option(self::CONFIG_OPTION, []);
        $base = $this->formatIntegrationBase($integration, $configs);

        $base['triggers'] = array_values(array_map(fn($t) => [
            'id'          => $t->getId(),
            'name'        => $t->getName(),
            'description' => $t->getDescription(),
        ], $integration->getTriggers()));

        $base['actions'] = array_values(array_map(fn($a) => [
            'id'          => $a->getId(),
            'name'        => $a->getName(),
            'description' => $a->getDescription(),
        ], $integration->getActions()));

        return new \WP_REST_Response($base);
    }

    public function saveConfig(\WP_REST_Request $request): \WP_REST_Response
    {
        $integration = $this->resolveIntegration($request);
        if ($integration instanceof \WP_REST_Response) {
            return $integration;
        }

        $id = $integration->getId();
        $body = $request->get_json_params();
        $credentials = $body['credentials'] ?? [];

        try {
            $credentials = $integration->connect($credentials);
        } catch (\RuntimeException $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        $configs = get_option(self::CONFIG_OPTION, []);
        $configs[$id] = [
            'enabled'     => true,
            'credentials' => $credentials,
        ];
        update_option(self::CONFIG_OPTION, $configs);

        return new \WP_REST_Response(['success' => true]);
    }

    public function deleteConfig(\WP_REST_Request $request): \WP_REST_Response
    {
        $integration = $this->resolveIntegration($request);
        if ($integration instanceof \WP_REST_Response) {
            return $integration;
        }

        $integration->disconnect();

        $id = $integration->getId();
        $configs = get_option(self::CONFIG_OPTION, []);
        unset($configs[$id]);
        update_option(self::CONFIG_OPTION, $configs);

        return new \WP_REST_Response(['success' => true]);
    }

    private function resolveIntegration(\WP_REST_Request $request): IntegrationInterface|\WP_REST_Response
    {
        $id = $request->get_param('id');
        $integration = $this->integrationRegistry->get($id);

        if (!$integration) {
            return new \WP_REST_Response(['error' => 'Integration not found'], 404);
        }

        return $integration;
    }

    public function triggers(): \WP_REST_Response
    {
        $iconMap = $this->buildIconMap('getTriggers');
        $triggers = [];

        foreach ($this->triggerRegistry->all() as $trigger) {
            $triggers[] = [
                'id'             => $trigger->getId(),
                'name'           => $trigger->getName(),
                'description'    => $trigger->getDescription(),
                'group'          => $trigger->getGroup(),
                'icon'           => $iconMap[$trigger->getId()] ?? '',
                'payload_schema' => ['type' => 'object', 'properties' => $trigger->getPayloadSchema()],
                'filter_schema'  => $trigger->getFilterSchema(),
            ];
        }

        return new \WP_REST_Response([
            'items' => $triggers,
            'total' => count($triggers),
        ]);
    }

    public function triggerFilterOptions(\WP_REST_Request $request): \WP_REST_Response
    {
        $triggerId = $request->get_param('triggerId');
        $field = $request->get_param('field');

        $trigger = $this->triggerRegistry->get($triggerId);
        if (!$trigger) {
            return new \WP_REST_Response(['error' => 'Trigger not found'], 404);
        }

        $options = $trigger->getFilterOptions($field);

        return new \WP_REST_Response([
            'options' => $options,
        ]);
    }

    public function actions(): \WP_REST_Response
    {
        $iconMap = $this->buildIconMap('getActions');
        $actions = [];
        $triggerIds = array_map(
            fn ($t) => $t->getId(),
            $this->triggerRegistry->all(),
        );

        foreach ($this->actionRegistry->all() as $action) {
            $schema = $action->getConfigSchema();
            $actions[] = [
                'id'            => $action->getId(),
                'name'          => $action->getName(),
                'description'   => $action->getDescription(),
                'group'         => $action->getGroup(),
                'icon'          => $iconMap[$action->getId()] ?? '',
                'config_schema' => [
                    'type'       => 'object',
                    'properties' => $schema,
                    'required'   => array_keys(array_filter($schema, fn($prop) => ($prop['required'] ?? false) === true)),
                ],
                'output_schema' => [
                    'type'       => 'object',
                    'properties' => $action->getOutputSchema(),
                ],
                'placeholders'  => $this->collectPlaceholders($action, $triggerIds),
            ];
        }

        return new \WP_REST_Response([
            'items' => $actions,
            'total' => count($actions),
        ]);
    }

    /** @param 'getTriggers'|'getActions' $method */
    private function buildIconMap(string $method): array
    {
        $map = [];
        foreach ($this->integrationRegistry->getAll() as $integration) {
            foreach ($integration->$method() as $item) {
                $map[$item->getId()] = $integration->getIcon();
            }
        }
        return $map;
    }

    private function collectPlaceholders(\WSms\Flow\Contracts\ActionInterface $action, array $triggerIds): array
    {
        $placeholders = [];
        foreach ($triggerIds as $triggerId) {
            $map = $action->getPlaceholders($triggerId);
            if ($map) {
                $placeholders[$triggerId] = $map;
            }
        }
        return $placeholders;
    }

    public function actionConfigOptions(\WP_REST_Request $request): \WP_REST_Response
    {
        $actionId = $request->get_param('actionId');
        $field = $request->get_param('field');

        $action = $this->actionRegistry->get($actionId);
        if (!$action) {
            return new \WP_REST_Response(['error' => 'Action not found'], 404);
        }

        $context = $request->get_query_params();
        unset($context['actionId'], $context['field']);
        $options = $action->getConfigOptions($field, $context);

        return new \WP_REST_Response([
            'options' => $options,
        ]);
    }

    public function saveSyncSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $integration = $this->resolveIntegration($request);
        if ($integration instanceof \WP_REST_Response) {
            return $integration;
        }

        if (!$integration instanceof SupportsContactSync) {
            return new \WP_REST_Response(['error' => 'Integration does not support sync'], 400);
        }

        $body = $request->get_json_params();
        $id = $integration->getId();

        $state = get_option(self::SYNC_STATE_OPTION, []);
        $state[$id]['sync_settings'] = [
            'auto_push'        => !empty($body['auto_push']),
            'push_tags'        => !empty($body['push_tags']),
            'poll_interval'    => (int) ($body['poll_interval'] ?? 3600),
            'poll_enabled'     => !empty($body['poll_enabled']),
            'default_list_id'  => sanitize_text_field($body['default_list_id'] ?? ''),
            'remove_on_delete' => !empty($body['remove_on_delete']),
        ];
        update_option(self::SYNC_STATE_OPTION, $state);

        if ($integration instanceof SupportsSuppressionSync) {
            as_unschedule_all_actions('wsms_suppression_poll', ['integration_id' => $id], 'wsms');
            if (!empty($body['poll_enabled'])) {
                $interval = (int) ($body['poll_interval'] ?? 3600);
                as_schedule_recurring_action(
                    time() + $interval,
                    $interval,
                    'wsms_suppression_poll',
                    ['integration_id' => $id],
                    'wsms',
                );
            }
        }

        return new \WP_REST_Response(['success' => true]);
    }

    public function triggerSync(\WP_REST_Request $request): \WP_REST_Response
    {
        $integration = $this->resolveIntegration($request);
        if ($integration instanceof \WP_REST_Response) {
            return $integration;
        }

        if (!$integration instanceof SupportsContactSync || !$this->queue) {
            return new \WP_REST_Response(['error' => 'Integration does not support sync'], 400);
        }

        $id = $integration->getId();
        $state = get_option(self::SYNC_STATE_OPTION, []);
        $config = $state[$id]['sync_settings'] ?? [];

        if (empty($config['default_list_id'])) {
            return new \WP_REST_Response(['error' => 'No default list configured'], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wsms_contacts';
        $dispatched = 0;
        $lastId = '';

        do {
            $batch = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE email IS NOT NULL AND email != '' AND status = 'subscribed' AND id > %s ORDER BY id ASC LIMIT 500",
                $lastId,
            ), ARRAY_A) ?: [];

            foreach ($batch as $contact) {
                $this->queue->dispatch(new MarketingPushContactJob($id, $contact));
                $dispatched++;
                $lastId = $contact['id'];
            }
        } while (count($batch) === 500);

        return new \WP_REST_Response(['success' => true, 'dispatched' => $dispatched]);
    }

    public function triggerPoll(\WP_REST_Request $request): \WP_REST_Response
    {
        $integration = $this->resolveIntegration($request);
        if ($integration instanceof \WP_REST_Response) {
            return $integration;
        }

        if (!$integration instanceof SupportsSuppressionSync || !$this->suppressionPoller) {
            return new \WP_REST_Response(['error' => 'Integration does not support suppression polling'], 400);
        }

        $events = $this->suppressionPoller->poll($integration->getId());

        return new \WP_REST_Response([
            'success' => true,
            'events'  => $events,
        ]);
    }

    public function integrationLists(\WP_REST_Request $request): \WP_REST_Response
    {
        $integration = $this->resolveIntegration($request);
        if ($integration instanceof \WP_REST_Response) {
            return $integration;
        }

        if (!$integration instanceof SupportsListManagement) {
            return new \WP_REST_Response(['error' => 'Integration does not support list management'], 400);
        }

        $state = get_option(self::SYNC_STATE_OPTION, []);
        $config = $state[$integration->getId()]['sync_settings'] ?? [];

        return new \WP_REST_Response([
            'lists' => $integration->getLists($config),
        ]);
    }

    public function listWebhookEndpoints(): \WP_REST_Response
    {
        $secrets = get_option(WebhookIntegration::SECRETS_OPTION, []);
        $endpoints = [];

        foreach ($secrets as $id => $entry) {
            $endpoints[] = [
                'id'         => $id,
                'label'      => $entry['label'] ?? '',
                'url'        => rest_url('wsms/v1/webhook/' . $id),
                'created_at' => $entry['created_at'] ?? '',
            ];
        }

        return new \WP_REST_Response(['endpoints' => $endpoints]);
    }

    public function createWebhookEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params();
        $label = sanitize_text_field($body['label'] ?? '');

        if (empty($label)) {
            return new \WP_REST_Response(['error' => 'Label is required'], 400);
        }

        $id = bin2hex(random_bytes(4));
        $secret = bin2hex(random_bytes(32));
        $createdAt = gmdate('c');

        $secrets = get_option(WebhookIntegration::SECRETS_OPTION, []);
        $secrets[$id] = [
            'secret'     => $secret,
            'label'      => $label,
            'created_at' => $createdAt,
        ];
        update_option(WebhookIntegration::SECRETS_OPTION, $secrets);

        return new \WP_REST_Response([
            'id'         => $id,
            'label'      => $label,
            'url'        => rest_url('wsms/v1/webhook/' . $id),
            'secret'     => $secret,
            'created_at' => $createdAt,
        ], 201);
    }

    public function deleteWebhookEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $endpointId = $request->get_param('endpointId');
        $secrets = get_option(WebhookIntegration::SECRETS_OPTION, []);

        if (!isset($secrets[$endpointId])) {
            return new \WP_REST_Response(['error' => 'Endpoint not found'], 404);
        }

        unset($secrets[$endpointId]);
        update_option(WebhookIntegration::SECRETS_OPTION, $secrets);

        return new \WP_REST_Response(['success' => true]);
    }

    private function formatIntegrationBase(IntegrationInterface $integration, array $configs, ?array $syncState = null): array
    {
        $base = [
            'id'          => $integration->getId(),
            'name'        => $integration->getName(),
            'description' => $integration->getDescription(),
            'category'    => $integration->getCategory(),
            'icon'        => $integration->getIcon(),
            'available'   => $integration->isAvailable(),
            'connected'   => $integration->isConnected(),
            'auth_type'   => $integration->getAuthType(),
            'auth_schema' => $integration->getAuthSchema(),
        ];

        $config = $configs[$integration->getId()] ?? null;
        if ($config && !empty($config['credentials'])) {
            $safe = array_diff_key($config['credentials'], array_flip(['bot_token', 'webhook_secret', 'api_key']));
            if ($safe) {
                $base['config'] = $safe;
            }
        }

        $base['capabilities'] = $this->getCapabilities($integration);

        if ($integration instanceof SupportsContactSync) {
            $syncState ??= get_option(self::SYNC_STATE_OPTION, []);
            $intState = $syncState[$integration->getId()] ?? [];
            $base['sync_settings'] = $intState['sync_settings'] ?? null;
            $base['sync_status'] = $intState['stats'] ?? null;
        }

        return $base;
    }

    private function formatIntegrationSummary(IntegrationInterface $integration, array $configs, ?array $syncState = null): array
    {
        return $this->formatIntegrationBase($integration, $configs, $syncState) + [
            'triggers' => count($integration->getTriggers()),
            'actions'  => count($integration->getActions()),
        ];
    }

    private function getCapabilities(IntegrationInterface $integration): array
    {
        return array_map(fn(array $cap) => [
            'id'         => $cap['id'],
            'name'       => IntegrationCapability::LABELS[$cap['id']] ?? $cap['id'],
            'supported'  => $cap['supported'],
            'note'       => $cap['note'] ?? null,
            'gateway_id' => $cap['gateway_id'] ?? null,
        ], $integration->getCapabilities());
    }
}
