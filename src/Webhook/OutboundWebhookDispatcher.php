<?php

namespace WSms\Webhook;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\ContactBouncedEvent;
use WSms\Event\Events\ContactComplainedEvent;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Dependencies\Psr\Log\LoggerInterface;

defined('ABSPATH') || exit;

class OutboundWebhookDispatcher
{
    public function __construct(
        private readonly EventDispatcherInterface $events,
        private readonly WebhookRepository $repository,
        private readonly QueueInterface $queue,
        private readonly LoggerInterface $logger,
        private readonly ContactRepositoryInterface $contacts,
    ) {
    }

    public function subscribe(): void
    {
        // Priority 100 = after domain listeners have finished processing.
        foreach (WebhookEventMap::getTypedEventClasses() as $eventClass) {
            $this->events->listen($eventClass, fn(object $event) => $this->handleEvent($event), 100);
        }

        add_action('wsms_contact_created', fn(string $id, array $data) => $this->handleHookEvent('contact.created', [$id, $data]), 10, 2);
        add_action('wsms_contact_updated', fn(string $id, array $data) => $this->handleHookEvent('contact.updated', [$id, $data]), 10, 2);
        add_action('wsms_subscription_confirmed', fn(string $contactId, string $formId) => $this->handleHookEvent('subscription.confirmed', [$contactId, $formId]), 10, 2);
    }

    public function handleEvent(object $event): void
    {
        $eventName = WebhookEventMap::getEventName(get_class($event));

        if ($eventName === null) {
            return;
        }

        $payload = WebhookEventMap::serializePayload($eventName, $event);

        if ($event instanceof ContactBouncedEvent || $event instanceof ContactComplainedEvent) {
            $payload = $this->enrichContactPayload($event->contactId, $payload);
        }

        $this->dispatchToMatchingWebhooks($eventName, $payload);
    }

    public function handleHookEvent(string $eventName, array $args): void
    {
        $payload = WebhookEventMap::serializeHookPayload($eventName, $args);
        $this->dispatchToMatchingWebhooks($eventName, $payload);
    }

    private function dispatchToMatchingWebhooks(string $eventName, array $data): void
    {
        $webhooks = $this->repository->findActiveForEvent($eventName);

        if (empty($webhooks)) {
            return;
        }

        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        foreach ($webhooks as $webhook) {
            $envelope = [
                'event'      => $eventName,
                'timestamp'  => $timestamp,
                'webhook_id' => $webhook['id'],
                'data'       => $data,
            ];

            try {
                $this->queue->dispatch(new DeliverWebhookJob(
                    webhookId: $webhook['id'],
                    url:       $webhook['url'],
                    event:     $eventName,
                    payload:   $envelope,
                    secret:    $webhook['secret'],
                ));
            } catch (\Throwable $e) {
                $this->logger->error('Failed to enqueue webhook delivery', [
                    'webhook_id' => $webhook['id'],
                    'event'      => $eventName,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    private function enrichContactPayload(string $contactId, array $payload): array
    {
        try {
            $contact = $this->contacts->find($contactId);

            if ($contact) {
                $payload['phone'] = $contact['phone'] ?? null;
                $payload['email'] = $contact['email'] ?? null;
            }
        } catch (\Throwable $e) {
            $this->logger->debug('Could not enrich webhook contact payload', [
                'contact_id' => $contactId,
                'error'      => $e->getMessage(),
            ]);
        }

        return $payload;
    }
}
