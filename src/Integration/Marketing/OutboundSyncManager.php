<?php

namespace WSms\Integration\Marketing;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Integration\Contracts\SupportsContactSync;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\MarketingPushContactJob;

defined('ABSPATH') || exit;

class OutboundSyncManager
{
    /** @param SupportsContactSync[] $integrations */
    public function __construct(
        private readonly array $integrations,
        private readonly QueueInterface $queue,
        private readonly ContactRepositoryInterface $contactRepository,
    ) {
    }

    public function listen(): void
    {
        add_action('wsms_contact_created', [$this, 'onContactCreated'], 10, 2);
        add_action('wsms_contact_updated', [$this, 'onContactUpdated'], 10, 2);
        add_action('wsms_contact_status_changed', [$this, 'onContactStatusChanged'], 10, 3);
        add_action('wsms_contact_tagged', [$this, 'onContactTagged'], 10, 2);
    }

    public function onContactCreated(string $contactId, array $data): void
    {
        $this->dispatchPushJobs($contactId);
    }

    public function onContactUpdated(string $contactId, array $data): void
    {
        $this->dispatchPushJobs($contactId);
    }

    public function onContactStatusChanged(string $contactId, string $oldStatus, string $newStatus): void
    {
        $this->dispatchPushJobs($contactId);
    }

    public function onContactTagged(string $contactId, string $tagId): void
    {
        $this->dispatchPushJobs($contactId);
    }

    private function dispatchPushJobs(string $contactId): void
    {
        $state = get_option('wsms_marketing_sync_state', []);
        $contact = null;

        foreach ($this->integrations as $integration) {
            $integrationId = $integration->getId();
            $settings = $state[$integrationId]['sync_settings'] ?? [];

            if (empty($settings['auto_push'])) {
                continue;
            }

            // Resolve contact once, reuse across integrations
            if ($contact === null) {
                $contact = $this->resolveContact($contactId, $settings);
                if ($contact === null) {
                    return;
                }
            }

            $this->queue->dispatch(new MarketingPushContactJob($integrationId, $contact));
        }
    }

    private function resolveContact(string $contactId, array $settings): ?array
    {
        $contact = $this->contactRepository->find($contactId);
        if (!$contact || empty($contact['email'])) {
            return null;
        }

        if (!empty($settings['push_tags'])) {
            $contact['tags'] = $this->contactRepository->getTags($contactId);
        }

        return $contact;
    }
}
