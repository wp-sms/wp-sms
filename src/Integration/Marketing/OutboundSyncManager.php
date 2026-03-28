<?php

namespace WSms\Integration\Marketing;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Integration\Contracts\SupportsContactSync;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\MarketingPushContactJob;

defined('ABSPATH') || exit;

class OutboundSyncManager
{
    /** @var array<string, true> Contacts already dispatched in this request */
    private array $dispatched = [];

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
        if (isset($this->dispatched[$contactId])) {
            return;
        }

        $state = get_option(ImportSyncManager::STATE_KEY, []);
        $contact = null;
        $anyNeedsTags = false;

        foreach ($this->integrations as $integration) {
            $settings = $state[$integration->getId()]['sync_settings'] ?? [];
            if (!empty($settings['auto_push']) && !empty($settings['push_tags'])) {
                $anyNeedsTags = true;
                break;
            }
        }

        foreach ($this->integrations as $integration) {
            $integrationId = $integration->getId();
            $settings = $state[$integrationId]['sync_settings'] ?? [];

            if (empty($settings['auto_push'])) {
                continue;
            }

            if ($contact === null) {
                $contact = $this->resolveContact($contactId, $anyNeedsTags);
                if ($contact === null) {
                    return;
                }
            }

            $this->queue->dispatch(new MarketingPushContactJob($integrationId, $contact));
        }

        $this->dispatched[$contactId] = true;
    }

    private function resolveContact(string $contactId, bool $includeTags): ?array
    {
        $contact = $this->contactRepository->find($contactId);
        if (!$contact || empty($contact['email'])) {
            return null;
        }

        if ($includeTags) {
            $contact['tags'] = $this->contactRepository->getTags($contactId);
        }

        return $contact;
    }
}
