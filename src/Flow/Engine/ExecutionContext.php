<?php

namespace WSms\Flow\Engine;

defined('ABSPATH') || exit;

class ExecutionContext
{
    private array $actionOutputs = [];
    private bool $hasDeferredWork = false;

    public function __construct(
        private array $payload,
    ) {
    }

    /** Original trigger payload + entity expansions. */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** Full data for template resolution and condition evaluation: payload + actions.* */
    public function getResolverData(): array
    {
        if (!$this->actionOutputs) {
            return $this->payload;
        }

        return $this->payload + ['actions' => $this->actionOutputs];
    }

    public function setActionOutput(string $actionId, array $output): void
    {
        $this->actionOutputs[$actionId] = $output;
    }

    public function markDeferredWork(): void
    {
        $this->hasDeferredWork = true;
    }

    public function hasDeferredWork(): bool
    {
        return $this->hasDeferredWork;
    }

    /** Merge additional data into payload (entity expansion, event data). */
    public function mergePayload(array $data): void
    {
        $this->payload = array_merge($this->payload, $data);
    }

    /** Serialize for queue jobs, delay scheduling, and wait state DB storage. */
    public function toArray(): array
    {
        return [
            'payload' => $this->payload,
            'action_outputs' => $this->actionOutputs,
        ];
    }

    /** Restore from serialized form (queue job deserialization, wait state resume). */
    public static function fromArray(array $data): self
    {
        $context = new self($data['payload'] ?? []);
        $context->actionOutputs = $data['action_outputs'] ?? [];
        return $context;
    }
}
