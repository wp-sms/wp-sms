<?php

namespace WSms\Rest;

use WSms\Flow\Action\ActionRegistry;
use WSms\Flow\Trigger\TriggerRegistry;
use WSms\Integration\IntegrationRegistry;

defined('ABSPATH') || exit;

class IntegrationController
{
    private const NAMESPACE = 'wsms/v1';

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

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(): \WP_REST_Response
    {
        $integrations = [];

        foreach ($this->integrationRegistry->getAll() as $integration) {
            $integrations[] = [
                'id'         => $integration->getId(),
                'name'       => $integration->getName(),
                'category'   => $integration->getCategory(),
                'icon'       => $integration->getIcon(),
                'available'  => $integration->isAvailable(),
                'auth_type'  => $integration->getAuthType(),
                'triggers'   => count($integration->getTriggers()),
                'actions'    => count($integration->getActions()),
            ];
        }

        return new \WP_REST_Response([
            'items' => $integrations,
            'total' => count($integrations),
        ]);
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
}
