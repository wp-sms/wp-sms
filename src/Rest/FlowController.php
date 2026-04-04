<?php

namespace WSms\Rest;

use WSms\Exception\NotFoundException;
use WSms\Exception\PersistenceException;
use WSms\Exception\ValidationException;
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
                'permission_callback' => $this->canViewSection('automation'),
                'args'                => [
                    'status' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'store'],
                'permission_callback' => $this->canManageSection('automation'),
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
                'permission_callback' => $this->canViewSection('automation'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>[A-Za-z0-9]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => $this->canViewSection('automation'),
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => $this->canManageSection('automation'),
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
                'permission_callback' => $this->canManageSection('automation'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>[A-Za-z0-9]+)/publish', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'publish'],
                'permission_callback' => $this->canManageSection('automation'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>[A-Za-z0-9]+)/test-trigger', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'testTrigger'],
                'permission_callback' => $this->canManageSection('automation'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>[A-Za-z0-9]+)/run', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'run'],
                'permission_callback' => $this->canManageSection('automation'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/flows/(?P<id>[A-Za-z0-9]+)/executions', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'executions'],
                'permission_callback' => $this->canViewSection('automation'),
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
                'permission_callback' => $this->canViewSection('automation'),
            ],
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $filters = [];
            if ($request->get_param('status')) {
                $filters['status'] = $request->get_param('status');
            }

            $flows = $this->flowRepository->findAll($filters);

            return $this->paginated(
                array_map(fn(Flow $f) => $f->toArray(), $flows),
                count($flows),
            );
        });
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
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
                throw new PersistenceException(__('Failed to save flow', 'wp-sms'));
            }

            return $this->created($saved->toArray());
        });
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $flow = $this->flowRepository->find($id);

            if (!$flow) {
                throw NotFoundException::entity('Flow', $id);
            }

            return $this->ok($flow->toArray());
        });
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $existing = $this->flowRepository->find($id);

            if (!$existing) {
                throw NotFoundException::entity('Flow', $id);
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

            return $this->ok($flow->toArray());
        });
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $deleted = $this->flowRepository->delete($request->get_param('id'));

            if (!$deleted) {
                throw NotFoundException::entity('Flow', $request->get_param('id'));
            }

            return $this->ok();
        });
    }

    public function publish(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $published = $this->flowRepository->publish($request->get_param('id'));

            if (!$published) {
                throw ValidationException::field('flow', __('Flow not found or has no steps', 'wp-sms'));
            }

            $flow = $this->flowRepository->find($request->get_param('id'));

            return $this->ok($flow->toArray());
        });
    }

    public function templates(): \WP_REST_Response
    {
        return $this->handle(function () {
            return new \WP_REST_Response([
                'items' => array_values(FlowTemplateRegistry::all()),
            ]);
        });
    }

    public function testTrigger(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $flow = $this->flowRepository->find($id);

            if (!$flow) {
                throw NotFoundException::entity('Flow', $id);
            }

            $trigger = $this->triggerRegistry->get($flow->getTriggerType());

            if (!$trigger) {
                throw NotFoundException::entity('Trigger', $flow->getTriggerType());
            }

            // Try to get real sample data from the trigger
            $samplePayload = $trigger->getSamplePayload();

            // Fall back to example values from the payload schema
            if ($samplePayload === null) {
                $samplePayload = $this->extractExamplesFromSchema($trigger->getPayloadSchema());
            }

            return $this->ok($samplePayload);
        });
    }

    public function run(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $flow = $this->flowRepository->find($id);

            if (!$flow) {
                throw NotFoundException::entity('Flow', $id);
            }

            if ($flow->getStatus() !== 'active') {
                throw ValidationException::field('flow', __('Flow must be active to run manually', 'wp-sms'));
            }

            $this->flowRunner->runSingleFlow($flow->getId(), [
                'triggered_by' => get_current_user_id(),
            ]);

            return $this->ok();
        });
    }

    public function executions(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $flowId = $request->get_param('id');
            $executions = $this->executionRepository->findByFlow(
                $flowId,
                (int) $request->get_param('per_page'),
                (int) $request->get_param('offset'),
            );

            return $this->paginated(
                array_map([$this, 'formatExecution'], $executions),
                $this->executionRepository->countByFlow($flowId),
            );
        });
    }

    public function executionDetail(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $execution = $this->executionRepository->find($request->get_param('execution_id'));

            if (!$execution || $execution['flow_id'] !== $request->get_param('id')) {
                throw NotFoundException::entity('Execution', $request->get_param('execution_id'));
            }

            return $this->ok($this->formatExecution($execution));
        });
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
