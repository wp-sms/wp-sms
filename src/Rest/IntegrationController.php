<?php

namespace WSms\Rest;

use WSms\Flow\Action\ActionRegistry;
use WSms\Flow\Trigger\TriggerRegistry;
use WSms\Integration\IntegrationRegistry;
use WSms\Integration\Contracts\IntegrationInterface;

defined('ABSPATH') || exit;

class IntegrationController extends Controller
{
    private const CONFIG_OPTION = 'wsms_integration_configs';

    public function __construct(
        private readonly IntegrationRegistry $integrationRegistry,
        private readonly TriggerRegistry $triggerRegistry,
        private readonly ActionRegistry $actionRegistry,
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
    }

    public function index(): \WP_REST_Response
    {
        $configs = get_option(self::CONFIG_OPTION, []);
        $integrations = [];

        foreach ($this->integrationRegistry->getAll() as $integration) {
            $integrations[] = $this->formatIntegrationSummary($integration, $configs);
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

    private function formatIntegrationBase(IntegrationInterface $integration, array $configs): array
    {
        $base = [
            'id'          => $integration->getId(),
            'name'        => $integration->getName(),
            'description' => $integration->getDescription(),
            'category'    => $integration->getCategory(),
            'icon'        => $integration->getIcon(),
            'available'   => $integration->isAvailable(),
            'connected'   => $this->isConnected($integration),
            'auth_type'   => $integration->getAuthType(),
            'auth_schema' => $integration->getAuthSchema(),
        ];

        $config = $configs[$integration->getId()] ?? null;
        if ($config && !empty($config['credentials'])) {
            // Only expose non-sensitive display fields to the frontend.
            $safe = array_diff_key($config['credentials'], array_flip(['bot_token', 'webhook_secret']));
            if ($safe) {
                $base['config'] = $safe;
            }
        }

        return $base;
    }

    private function formatIntegrationSummary(IntegrationInterface $integration, array $configs): array
    {
        return $this->formatIntegrationBase($integration, $configs) + [
            'triggers' => count($integration->getTriggers()),
            'actions'  => count($integration->getActions()),
        ];
    }

    private function isConnected(IntegrationInterface $integration): bool
    {
        return $integration->isConnected();
    }
}
