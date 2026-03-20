<?php

namespace WSms\Rest;

use WSms\Flow\Contracts\FlowRepositoryInterface;
use WSms\Flow\Contracts\Flow;
use WSms\Flow\Engine\FlowRunner;
use WSms\Flow\FlowTemplateRegistry;
use WSms\Flow\Storage\FlowExecutionRepository;
use WSms\Flow\Trigger\TriggerRegistry;

defined('ABSPATH') || exit;

class FlowController extends Controller
{
    public function __construct(
        private readonly FlowRepositoryInterface $flowRepository,
        private readonly FlowExecutionRepository $executionRepository,
        private readonly TriggerRegistry $triggerRegistry,
        private readonly FlowRunner $flowRunner,
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

        register_rest_route(self::NAMESPACE, '/flows/templates', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'templates'],
                'permission_callback' => [$this, 'canManage'],
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

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>[A-Za-z0-9]+)/test-trigger', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'testTrigger'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>[A-Za-z0-9]+)/run', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'run'],
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

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>[A-Za-z0-9]+)/executions/(?P<execution_id>[A-Za-z0-9]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'executionDetail'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
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

        if (!$saved) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'save_failed',
                'message' => __('Failed to save flow', 'wp-sms'),
            ], 500);
        }

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

    public function templates(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'items' => array_values(FlowTemplateRegistry::all()),
        ]);
    }

    public function testTrigger(\WP_REST_Request $request): \WP_REST_Response
    {
        $flow = $this->flowRepository->find($request->get_param('id'));

        if (!$flow) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Flow not found', 'wp-sms'),
            ], 404);
        }

        $trigger = $this->triggerRegistry->get($flow->getTriggerType());

        if (!$trigger) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'trigger_not_found',
                'message' => __('Trigger not found', 'wp-sms'),
            ], 404);
        }

        // Try to get real sample data from the trigger
        $samplePayload = $trigger->getSamplePayload();

        // Fall back to example values from the payload schema
        if ($samplePayload === null) {
            $samplePayload = $this->extractExamplesFromSchema($trigger->getPayloadSchema());
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $samplePayload,
        ]);
    }

    public function run(\WP_REST_Request $request): \WP_REST_Response
    {
        $flow = $this->flowRepository->find($request->get_param('id'));

        if (!$flow) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Flow not found', 'wp-sms'),
            ], 404);
        }

        if ($flow->getStatus() !== 'active') {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'flow_not_active',
                'message' => __('Flow must be active to run manually', 'wp-sms'),
            ], 400);
        }

        $this->flowRunner->runSingleFlow($flow->getId(), [
            'triggered_by' => get_current_user_id(),
        ]);

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Flow execution started', 'wp-sms'),
        ]);
    }

    public function executions(\WP_REST_Request $request): \WP_REST_Response
    {
        $flowId = $request->get_param('id');
        $executions = $this->executionRepository->findByFlow(
            $flowId,
            (int) $request->get_param('per_page'),
            (int) $request->get_param('offset'),
        );

        return new \WP_REST_Response([
            'items' => array_map([$this, 'formatExecution'], $executions),
            'total' => $this->executionRepository->countByFlow($flowId),
        ]);
    }

    public function executionDetail(\WP_REST_Request $request): \WP_REST_Response
    {
        $execution = $this->executionRepository->find($request->get_param('execution_id'));

        if (!$execution || $execution['flow_id'] !== $request->get_param('id')) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Execution not found', 'wp-sms'),
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $this->formatExecution($execution),
        ]);
    }

    private function formatExecution(array $row): array
    {
        return [
            'id'           => $row['id'],
            'flow_id'      => $row['flow_id'],
            'status'       => $row['status'],
            'trigger_data' => json_decode($row['trigger_data'] ?? '{}', true),
            'step_logs'    => json_decode($row['step_logs'] ?? '[]', true),
            'error'        => $row['error'] ?? null,
            'started_at'   => $row['started_at'],
            'completed_at' => $row['completed_at'] ?? null,
        ];
    }

    private function extractExamplesFromSchema(array $schema): array
    {
        $result = [];
        foreach ($schema as $key => $prop) {
            if (($prop['type'] ?? '') === 'object' && isset($prop['properties'])) {
                $result[$key] = $this->extractExamplesFromSchema($prop['properties']);
            } elseif (isset($prop['example'])) {
                $result[$key] = $prop['example'];
            }
        }
        return $result;
    }
}
