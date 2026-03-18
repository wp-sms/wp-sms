<?php

namespace WSms\Campaign;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;

defined('ABSPATH') || exit;

class Campaign
{
    public function __construct(
        private string $id,
        private string $name,
        private string $channel,
        private ?string $gatewayId,
        private string $status,
        private string $body,
        private array $audience,
        private ?string $subject = null,
        private ?array $compliance = null,
        private ?\DateTimeInterface $sendAt = null,
        private string $timezone = 'UTC',
        private ?array $recurrence = null,
        private ?array $quietHours = null,
        private ?string $parentId = null,
        private int $totalRecipients = 0,
        private int $sentCount = 0,
        private int $deliveredCount = 0,
        private int $failedCount = 0,
        private int $skippedCount = 0,
        private float $totalCost = 0,
        private ?string $lastProcessedId = null,
        private ?int $createdBy = null,
        private ?\DateTimeInterface $startedAt = null,
        private ?\DateTimeInterface $completedAt = null,
        private ?\DateTimeInterface $cancelledAt = null,
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

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getGatewayId(): ?string
    {
        return $this->gatewayId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getAudience(): array
    {
        return $this->audience;
    }

    public function getCompliance(): ?array
    {
        return $this->compliance;
    }

    public function getSendAt(): ?\DateTimeInterface
    {
        return $this->sendAt;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function getRecurrence(): ?array
    {
        return $this->recurrence;
    }

    public function getQuietHours(): ?array
    {
        return $this->quietHours;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getTotalRecipients(): int
    {
        return $this->totalRecipients;
    }

    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    public function getDeliveredCount(): int
    {
        return $this->deliveredCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getTotalCost(): float
    {
        return $this->totalCost;
    }

    public function getLastProcessedId(): ?string
    {
        return $this->lastProcessedId;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function getCancelledAt(): ?\DateTimeInterface
    {
        return $this->cancelledAt;
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'channel'           => $this->channel,
            'gateway_id'        => $this->gatewayId,
            'status'            => $this->status,
            'subject'           => $this->subject,
            'body'              => $this->body,
            'audience'          => $this->audience,
            'compliance'        => $this->compliance,
            'send_at'           => $this->sendAt?->format('Y-m-d H:i:s'),
            'timezone'          => $this->timezone,
            'recurrence'        => $this->recurrence,
            'quiet_hours'       => $this->quietHours,
            'parent_id'         => $this->parentId,
            'total_recipients'  => $this->totalRecipients,
            'sent_count'        => $this->sentCount,
            'delivered_count'   => $this->deliveredCount,
            'failed_count'      => $this->failedCount,
            'skipped_count'     => $this->skippedCount,
            'total_cost'        => $this->totalCost,
            'last_processed_id' => $this->lastProcessedId,
            'created_by'        => $this->createdBy,
            'started_at'        => $this->startedAt?->format('Y-m-d H:i:s'),
            'completed_at'      => $this->completedAt?->format('Y-m-d H:i:s'),
            'cancelled_at'      => $this->cancelledAt?->format('Y-m-d H:i:s'),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            name: $data['name'],
            channel: $data['channel'],
            gatewayId: $data['gateway_id'] ?? null,
            status: $data['status'] ?? 'draft',
            body: $data['body'] ?? '',
            audience: is_string($data['audience'] ?? null) ? json_decode($data['audience'], true) : ($data['audience'] ?? []),
            subject: $data['subject'] ?? null,
            compliance: isset($data['compliance']) ? (is_string($data['compliance']) ? json_decode($data['compliance'], true) : $data['compliance']) : null,
            sendAt: isset($data['send_at']) ? new \DateTimeImmutable($data['send_at']) : null,
            timezone: $data['timezone'] ?? 'UTC',
            recurrence: isset($data['recurrence']) ? (is_string($data['recurrence']) ? json_decode($data['recurrence'], true) : $data['recurrence']) : null,
            quietHours: isset($data['quiet_hours']) ? (is_string($data['quiet_hours']) ? json_decode($data['quiet_hours'], true) : $data['quiet_hours']) : null,
            parentId: $data['parent_id'] ?? null,
            totalRecipients: (int) ($data['total_recipients'] ?? 0),
            sentCount: (int) ($data['sent_count'] ?? 0),
            deliveredCount: (int) ($data['delivered_count'] ?? 0),
            failedCount: (int) ($data['failed_count'] ?? 0),
            skippedCount: (int) ($data['skipped_count'] ?? 0),
            totalCost: (float) ($data['total_cost'] ?? 0),
            lastProcessedId: $data['last_processed_id'] ?? null,
            createdBy: isset($data['created_by']) ? (int) $data['created_by'] : null,
            startedAt: isset($data['started_at']) ? new \DateTimeImmutable($data['started_at']) : null,
            completedAt: isset($data['completed_at']) ? new \DateTimeImmutable($data['completed_at']) : null,
            cancelledAt: isset($data['cancelled_at']) ? new \DateTimeImmutable($data['cancelled_at']) : null,
        );
    }
}
