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

        register_rest_route(self::NAMESPACE, '/actions', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'actions'],
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
        $triggers = [];

        foreach ($this->triggerRegistry->all() as $trigger) {
            $triggers[] = [
                'id'             => $trigger->getId(),
                'name'           => $trigger->getName(),
                'group'          => $trigger->getGroup(),
                'payload_schema' => $trigger->getPayloadSchema(),
            ];
        }

        return new \WP_REST_Response([
            'items' => $triggers,
            'total' => count($triggers),
        ]);
    }

    public function actions(): \WP_REST_Response
    {
        $actions = [];

        foreach ($this->actionRegistry->all() as $action) {
            $actions[] = [
                'id'            => $action->getId(),
                'name'          => $action->getName(),
                'group'         => $action->getGroup(),
                'config_schema' => $action->getConfigSchema(),
            ];
        }

        return new \WP_REST_Response([
            'items' => $actions,
            'total' => count($actions),
        ]);
    }
}
