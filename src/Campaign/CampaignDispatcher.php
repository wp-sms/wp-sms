<?php

namespace WSms\Campaign;

use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\CampaignCancelledEvent;
use WSms\Event\Events\CampaignCompletedEvent;
use WSms\Event\Events\CampaignStartedEvent;
use WSms\Log\Contracts\MessageLoggerInterface;
use WSms\Messaging\Contracts\TemplateEngineInterface;
use WSms\Messaging\Message\Message;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\ProcessCampaignBatchJob;
use WSms\Queue\Job\ScheduleCampaignRecurrenceJob;

defined('ABSPATH') || exit;

class CampaignDispatcher
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly AudienceResolver $audienceResolver,
        private readonly MessageDispatcher $messageDispatcher,
        private readonly MessageLoggerInterface $messageLogger,
        private readonly TemplateEngineInterface $templateEngine,
        private readonly GatewayRegistry $gatewayRegistry,
        private readonly QueueInterface $queue,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly QuietHoursGuard $quietHoursGuard,
        private readonly GatewayThrottler $throttler,
    ) {
    }

    /**
     * Dispatch a campaign for sending (entry point for "send now" or scheduler).
     */
    public function dispatch(string $campaignId): bool
    {
        $campaign = $this->campaignRepository->find($campaignId);
        if (!$campaign) {
            return false;
        }

        if (!in_array($campaign->getStatus(), ['draft', 'scheduled'])) {
            return false;
        }

        // Verify gateway is still configured
        $gatewayId = $campaign->getGatewayId();
        if ($gatewayId && !$this->gatewayRegistry->get($gatewayId)) {
            $this->campaignRepository->updateStatus($campaignId, 'failed', [
                'completed_at' => current_time('mysql'),
            ]);
            return false;
        }

        // Check audience count
        $audienceCount = $this->audienceResolver->count($campaign->getAudience(), $campaign->getChannel());
        if ($audienceCount === 0) {
            $this->campaignRepository->updateStatus($campaignId, 'sent', [
                'total_recipients' => 0,
                'completed_at'     => current_time('mysql'),
                'started_at'       => current_time('mysql'),
            ]);
            return true;
        }

        // Count skipped contacts
        $skippedCount = $this->audienceResolver->countSkipped($campaign->getAudience(), $campaign->getChannel());

        // Update status to sending
        $now = current_time('mysql');
        $this->campaignRepository->updateStatus($campaignId, 'sending', [
            'started_at'       => $now,
            'total_recipients' => $audienceCount,
            'skipped_count'    => $skippedCount,
        ]);

        $this->eventDispatcher->dispatch(new CampaignStartedEvent(
            campaignId: $campaignId,
            channel: $campaign->getChannel(),
            totalRecipients: $audienceCount,
        ));

        // Queue first batch
        $this->queue->dispatch(new ProcessCampaignBatchJob($campaignId));

        return true;
    }

    /**
     * Process a single batch of recipients. Called by the batch job handler.
     */
    public function processBatch(string $campaignId, ?string $afterId = null, int $batchSize = 500): void
    {
        $campaign = $this->campaignRepository->find($campaignId);
        if (!$campaign || $campaign->getStatus() !== 'sending') {
            return;
        }

        $gatewayId = $campaign->getGatewayId();
        $batch = $this->audienceResolver->resolve(
            $campaign->getAudience(),
            $campaign->getChannel(),
            $batchSize,
            $afterId,
        );

        $sentInBatch = 0;
        $failedInBatch = 0;

        // Batch dedup: load all already-sent recipients in one query
        $addresses = array_unique(array_filter(array_column($batch->recipients, 'recipient')));
        $alreadySent = $this->messageLogger->findSentRecipients($campaignId, $addresses);

        // Compute opt-out text once (same for all recipients)
        $compliance = $campaign->getCompliance() ?? [];
        $optOutSuffix = (!empty($compliance['append_opt_out']) && !empty($compliance['opt_out_text']))
            ? "\n\n" . $compliance['opt_out_text']
            : '';

        // Check quiet hours once (same for all recipients in this batch)
        $quietHours = $campaign->getQuietHours();
        $isQuiet = $quietHours && $this->quietHoursGuard->isQuietNow($quietHours);

        foreach ($batch->recipients as $recipient) {
            $recipientAddress = $recipient['recipient'] ?? '';
            if ($recipientAddress === '' || isset($alreadySent[$recipientAddress])) {
                continue;
            }

            // Render body with contact variables
            $templateData = [
                'first_name'    => $recipient['first_name'] ?? '',
                'last_name'     => $recipient['last_name'] ?? '',
                'phone'         => $recipient['recipient'] ?? '',
                'email'         => $recipient['recipient'] ?? '',
                'contact_id'    => $recipient['contact_id'] ?? '',
            ];
            if (!empty($recipient['custom_fields'])) {
                $templateData = array_merge($templateData, $recipient['custom_fields']);
            }

            $body = $this->templateEngine->render($campaign->getBody(), $templateData) . $optOutSuffix;

            if ($isQuiet) {
                $this->messageLogger->logSend(
                    gatewayId: $gatewayId ?? 'default',
                    channel: $campaign->getChannel(),
                    recipient: $recipientAddress,
                    body: $body,
                    status: 'queued',
                    subject: $campaign->getSubject(),
                    type: 'campaign',
                    campaignId: $campaignId,
                );
                $sentInBatch++;
                continue;
            }

            $message = new Message(
                channel: $campaign->getChannel(),
                recipient: $recipientAddress,
                body: $body,
                meta: ['subject' => $campaign->getSubject()],
                campaignId: $campaignId,
            );

            try {
                $this->messageDispatcher->sendQueued($message);
                $sentInBatch++;
            } catch (\Throwable) {
                $failedInBatch++;
            }
        }

        // Update campaign counters atomically
        if ($sentInBatch > 0) {
            $this->campaignRepository->incrementCounters($campaignId, 'sent_count', $sentInBatch);
        }
        if ($failedInBatch > 0) {
            $this->campaignRepository->incrementCounters($campaignId, 'failed_count', $failedInBatch);
        }

        // Update cursor
        if ($batch->lastId) {
            $this->campaignRepository->updateLastProcessedId($campaignId, $batch->lastId);
        }

        // Queue next batch or complete
        if ($batch->hasMore) {
            $this->queue->dispatch(new ProcessCampaignBatchJob(
                $campaignId,
                $batch->lastId,
                $batchSize,
            ));
        } else {
            $this->completeCampaign($campaignId);
        }
    }

    /**
     * Cancel a sending or scheduled campaign.
     */
    public function cancel(string $campaignId): bool
    {
        $campaign = $this->campaignRepository->find($campaignId);
        if (!$campaign || !in_array($campaign->getStatus(), ['sending', 'scheduled'])) {
            return false;
        }

        $this->campaignRepository->updateStatus($campaignId, 'cancelled', [
            'cancelled_at' => current_time('mysql'),
        ]);

        $this->eventDispatcher->dispatch(new CampaignCancelledEvent($campaignId));

        return true;
    }

    /**
     * Pause a sending campaign.
     */
    public function pause(string $campaignId): bool
    {
        $campaign = $this->campaignRepository->find($campaignId);
        if (!$campaign || $campaign->getStatus() !== 'sending') {
            return false;
        }

        $this->campaignRepository->updateStatus($campaignId, 'paused');

        return true;
    }

    /**
     * Resume a paused campaign from where it left off.
     */
    public function resume(string $campaignId): bool
    {
        $campaign = $this->campaignRepository->find($campaignId);
        if (!$campaign || $campaign->getStatus() !== 'paused') {
            return false;
        }

        $this->campaignRepository->updateStatus($campaignId, 'sending');

        // Re-dispatch from last processed ID
        $this->queue->dispatch(new ProcessCampaignBatchJob(
            $campaignId,
            $campaign->getLastProcessedId(),
        ));

        return true;
    }

    /**
     * Schedule a campaign for future sending.
     */
    public function schedule(string $campaignId, \DateTimeInterface $sendAt): bool
    {
        $campaign = $this->campaignRepository->find($campaignId);
        if (!$campaign || $campaign->getStatus() !== 'draft') {
            return false;
        }

        $this->campaignRepository->updateStatus($campaignId, 'scheduled', [
            'send_at' => $sendAt->format('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Handle recurring campaign: clone the completed campaign with next send_at.
     */
    public function scheduleRecurrence(string $campaignId): void
    {
        $campaign = $this->campaignRepository->find($campaignId);
        if (!$campaign || !$campaign->getRecurrence()) {
            return;
        }

        $recurrence = $campaign->getRecurrence();
        $frequency = $recurrence['frequency'] ?? null;
        $endDate = isset($recurrence['end_date']) ? new \DateTimeImmutable($recurrence['end_date']) : null;

        if (!$frequency) {
            return;
        }

        // Calculate next send_at
        $lastSendAt = $campaign->getSendAt() ?? new \DateTimeImmutable();
        $nextSendAt = match ($frequency) {
            'daily'   => $lastSendAt->modify('+1 day'),
            'weekly'  => $lastSendAt->modify('+1 week'),
            'monthly' => $lastSendAt->modify('+1 month'),
            default   => null,
        };

        if (!$nextSendAt || ($endDate && $nextSendAt > $endDate)) {
            return;
        }

        // Check no other occurrence is still sending
        $existing = $this->campaignRepository->findAll(['status' => 'sending']);
        foreach ($existing as $c) {
            if ($c->getParentId() === $campaign->getParentId() || $c->getParentId() === $campaign->getId()) {
                return; // Another occurrence is still sending
            }
        }

        // Clone as new scheduled campaign
        $parentId = $campaign->getParentId() ?? $campaign->getId();
        $clone = new Campaign(
            id: '',
            name: $campaign->getName(),
            channel: $campaign->getChannel(),
            gatewayId: $campaign->getGatewayId(),
            status: 'scheduled',
            body: $campaign->getBody(),
            audience: $campaign->getAudience(),
            subject: $campaign->getSubject(),
            compliance: $campaign->getCompliance(),
            sendAt: $nextSendAt,
            timezone: $campaign->getTimezone(),
            recurrence: $campaign->getRecurrence(),
            quietHours: $campaign->getQuietHours(),
            parentId: $parentId,
            createdBy: $campaign->getCreatedBy(),
        );

        $this->campaignRepository->save($clone);
    }

    private function completeCampaign(string $campaignId): void
    {
        $this->campaignRepository->updateStatus($campaignId, 'sent', [
            'completed_at' => current_time('mysql'),
        ]);

        $campaign = $this->campaignRepository->find($campaignId);
        if (!$campaign) {
            return;
        }

        $this->eventDispatcher->dispatch(new CampaignCompletedEvent(
            campaignId: $campaignId,
            sentCount: $campaign->getSentCount(),
            failedCount: $campaign->getFailedCount(),
            skippedCount: $campaign->getSkippedCount(),
        ));

        // Handle recurring campaigns
        if ($campaign->getRecurrence()) {
            $this->queue->dispatch(new ScheduleCampaignRecurrenceJob($campaignId));
        }
    }

}
