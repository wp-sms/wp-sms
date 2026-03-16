<?php

namespace WSms\Flow\Contracts;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;

defined('ABSPATH') || exit;

class Flow
{
    public function __construct(
        private string $id,
        private string $name,
        private string $triggerType,
        private array $triggerConfig,
        private array $steps,
        private string $status = 'draft',
        private ?array $publishedSteps = null,
        private ?\DateTimeInterface $publishedAt = null,
        private ?string $description = null,
        private int $priority = 0,
        private ?int $createdBy = null,
    ) {
        if ($this->id === '') {
            $this->id = (string) new Ulid();
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTriggerType(): string
    {
        return $this->triggerType;
    }

    public function getTriggerConfig(): array
    {
        return $this->triggerConfig;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getPublishedSteps(): ?array
    {
        return $this->publishedSteps;
    }

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function getActiveSteps(): array
    {
        return $this->publishedSteps ?? $this->steps;
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'trigger_type'    => $this->triggerType,
            'trigger_config'  => $this->triggerConfig,
            'steps'           => $this->steps,
            'status'          => $this->status,
            'published_steps' => $this->publishedSteps,
            'published_at'    => $this->publishedAt?->format('Y-m-d H:i:s'),
            'description'     => $this->description,
            'priority'        => $this->priority,
            'created_by'      => $this->createdBy,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            name: $data['name'],
            triggerType: $data['trigger_type'],
            triggerConfig: is_string($data['trigger_config'] ?? null) ? json_decode($data['trigger_config'], true) : ($data['trigger_config'] ?? []),
            steps: is_string($data['steps'] ?? null) ? json_decode($data['steps'], true) : ($data['steps'] ?? []),
            status: $data['status'] ?? 'draft',
            publishedSteps: isset($data['published_steps']) ? (is_string($data['published_steps']) ? json_decode($data['published_steps'], true) : $data['published_steps']) : null,
            publishedAt: isset($data['published_at']) ? new \DateTimeImmutable($data['published_at']) : null,
            description: $data['description'] ?? null,
            priority: (int) ($data['priority'] ?? 0),
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
        );
    }
}
