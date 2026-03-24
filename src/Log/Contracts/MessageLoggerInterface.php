<?php

namespace WSms\Log\Contracts;

defined('ABSPATH') || exit;

interface MessageLoggerInterface
{
    public function logSend(
        string $gatewayId,
        string $channel,
        string $recipient,
        string $body,
        string $status,
        ?string $executionId = null,
        ?string $subject = null,
        ?string $providerId = null,
        ?string $error = null,
        ?float $cost = null,
        string $type = 'transactional',
        ?string $campaignId = null,
    ): string;

    public function updateStatus(string $logId, string $status, ?string $error = null, ?string $providerId = null): void;

    public function findByProviderId(string $gatewayId, string $providerId): ?array;

    public function findByExecution(string $executionId): array;

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array;

    public function count(array $filters = []): int;

    public function deleteOlderThan(int $days): int;

    /** @return array{total: int, delivered: int, failed: int, sent: int, queued: int, total_cost: float} */
    public function getCampaignStats(string $campaignId): array;

    /**
     * Find message logs matching any of the given recipients.
     *
     * @param string[] $recipients Email and/or phone values to match.
     * @return list<array<string, mixed>>
     */
    public function findByRecipients(array $recipients, int $limit = 20, int $offset = 0): array;

    /**
     * Find already-sent recipient addresses for a campaign (batch dedup).
     *
     * @param string[] $addresses Recipient addresses to check.
     * @return array<string, true> Keyed by recipient address.
     */
    public function findSentRecipients(string $campaignId, array $addresses): array;
}
