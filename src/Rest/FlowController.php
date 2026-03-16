<?php

namespace WSms\Rest;

use WSms\Flow\Contracts\FlowRepositoryInterface;
use WSms\Flow\Contracts\Flow;
use WSms\Flow\Storage\FlowExecutionRepository;

defined('ABSPATH') || exit;

class FlowController
{
    private const NAMESPACE = 'wsms/v1';

    public function __construct(
        private readonly FlowRepositoryInterface $flowRepository,
        private readonly FlowExecutionRepository $executionRepository,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/flows', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'status' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'store'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'name'           => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'trigger_type'   => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'trigger_config' => ['type' => 'object'],
                    'steps'          => ['type' => 'array'],
                    'description'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
                    'priority'       => ['type' => 'integer'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>[A-Za-z0-9]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'name'           => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'trigger_type'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'trigger_config' => ['type' => 'object'],
                    'steps'          => ['type' => 'array'],
                    'status'         => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'description'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
                    'priority'       => ['type' => 'integer'],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'destroy'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>[A-Za-z0-9]+)/publish', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'publish'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>[A-Za-z0-9]+)/executions', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'executions'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'per_page' => ['type' => 'integer', 'default' => 50],
                    'offset'   => ['type' => 'integer', 'default' => 0],
                ],
            ],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $filters = [];
        if ($request->get_param('status')) {
            $filters['status'] = $request->get_param('status');
        }

        $flows = $this->flowRepository->findAll($filters);

        return new \WP_REST_Response([
            'items' => array_map(fn(Flow $f) => $f->toArray(), $flows),
            'total' => count($flows),
        ]);
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_params();

        $flow = new Flow(
            id: '',
            name: $params['name'] ?? '',
            triggerType: $params['trigger_type'] ?? '',
            triggerConfig: $params['trigger_config'] ?? [],
            steps: $params['steps'] ?? [],
            description: $params['description'] ?? null,
            priority: (int) ($params['priority'] ?? 0),
            createdBy: get_current_user_id(),
        );

        $id = $this->flowRepository->save($flow);
        $saved = $this->flowRepository->find($id);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $saved->toArray(),
        ], 201);
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $flow = $this->flowRepository->find($request->get_param('id'));

        if (!$flow) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Flow not found', 'wp-sms'),
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $flow->toArray(),
        ]);
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $existing = $this->flowRepository->find($id);

        if (!$existing) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Flow not found', 'wp-sms'),
            ], 404);
        }

        $params = $request->get_params();

        $flow = new Flow(
            id: $id,
            name: $params['name'] ?? $existing->getName(),
            triggerType: $params['trigger_type'] ?? $existing->getTriggerType(),
            triggerConfig: $params['trigger_config'] ?? $existing->getTriggerConfig(),
            steps: $params['steps'] ?? $existing->getSteps(),
            status: $params['status'] ?? $existing->getStatus(),
            publishedSteps: $existing->getPublishedSteps(),
            publishedAt: $existing->getPublishedAt(),
            description: $params['description'] ?? $existing->getDescription(),
            priority: (int) ($params['priority'] ?? $existing->getPriority()),
            createdBy: $existing->getCreatedBy(),
        );

        $this->flowRepository->save($flow);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $flow->toArray(),
        ]);
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $deleted = $this->flowRepository->delete($request->get_param('id'));

        if (!$deleted) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Flow not found', 'wp-sms'),
            ], 404);
        }

        return new \WP_REST_Response(['success' => true]);
    }

    public function publish(\WP_REST_Request $request): \WP_REST_Response
    {
        $published = $this->flowRepository->publish($request->get_param('id'));

        if (!$published) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_flow',
                'message' => __('Flow not found or has no steps', 'wp-sms'),
            ], 400);
        }

        $flow = $this->flowRepository->find($request->get_param('id'));

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $flow->toArray(),
        ]);
    }

    public function executions(\WP_REST_Request $request): \WP_REST_Response
    {
        $executions = $this->executionRepository->findByFlow(
            $request->get_param('id'),
            (int) $request->get_param('per_page'),
            (int) $request->get_param('offset'),
        );

        return new \WP_REST_Response([
            'items' => $executions,
            'total' => count($executions),
        ]);
    }
}
