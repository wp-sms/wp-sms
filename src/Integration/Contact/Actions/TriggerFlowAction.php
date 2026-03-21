<?php

namespace WSms\Integration\Contact\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Flow\Contracts\FlowRepositoryInterface;
use WSms\Flow\Engine\FlowRunner;

defined('ABSPATH') || exit;

class TriggerFlowAction extends AbstractAction
{
    private const MAX_CHAIN_DEPTH = 5;

    public function __construct(
        private readonly FlowRepositoryInterface $flowRepository,
        private readonly FlowRunner $flowRunner,
    ) {
    }

    public function getId(): string
    {
        return 'trigger_flow';
    }

    public function getName(): string
    {
        return __('Trigger Another Flow', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Start another flow as a sub-flow with a custom payload', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WSMS';
    }

    public function getOutputSchema(): array
    {
        return [
            'flow_id'   => ['type' => 'string', 'title' => 'Flow ID'],
            'flow_name' => ['type' => 'string', 'title' => 'Flow Name'],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'flow_id' => [
                'type'        => 'string',
                'label'       => __('Target Flow', 'wp-sms'),
                'description' => __('The flow to trigger', 'wp-sms'),
                'required'    => true,
                'dynamic'     => true,
            ],
            'payload' => [
                'type'        => 'object',
                'label'       => __('Payload', 'wp-sms'),
                'description' => __('Custom key-value data to pass to the triggered flow', 'wp-sms'),
            ],
        ];
    }

    public function getConfigOptions(string $fieldKey, array $context = []): array
    {
        if ($fieldKey === 'flow_id') {
            $flows = $this->flowRepository->findAll(['status' => 'active']);
            return array_map(fn($flow) => [
                'value' => $flow->getId(),
                'label' => $flow->getName(),
            ], $flows);
        }

        return [];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $flowId = $config['flow_id'] ?? '';
        if (empty($flowId)) {
            return ActionResult::failure('Target flow ID is required');
        }

        $flow = $this->flowRepository->find($flowId);
        if (!$flow) {
            return ActionResult::failure('Target flow not found');
        }

        if ($flow->getStatus() !== 'active') {
            return ActionResult::failure('Target flow is not active');
        }

        // Circular reference detection
        $parentFlowIds = $payload['_parent_flow_ids'] ?? [];
        if (in_array($flowId, $parentFlowIds, true)) {
            return ActionResult::failure('Circular flow reference detected');
        }

        // Chain depth limit
        if (count($parentFlowIds) >= self::MAX_CHAIN_DEPTH) {
            return ActionResult::failure('Maximum flow chain depth (' . self::MAX_CHAIN_DEPTH . ') exceeded');
        }

        // Build fresh payload for the child flow (clean separation)
        $childPayload = is_array($config['payload'] ?? null) ? $config['payload'] : [];
        $childPayload['_parent_flow_ids'] = array_merge($parentFlowIds, [$payload['_flow_id'] ?? 'unknown']);
        $childPayload['triggered_by_flow'] = $payload['_flow_id'] ?? 'unknown';

        $this->flowRunner->runSingleFlow($flowId, $childPayload);

        return ActionResult::success([
            'flow_id'   => $flowId,
            'flow_name' => $flow->getName(),
        ]);
    }
}
