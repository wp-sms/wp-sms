<?php

namespace WSms\Integration\Marketing;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Integration\Contracts\SupportsSuppressionSync;

defined('ABSPATH') || exit;

class SuppressionPoller
{
    /** @param SupportsSuppressionSync[] $integrations */
    public function __construct(
        private readonly array $integrations,
        private readonly ContactRepositoryInterface $contactRepository,
    ) {
    }

    /** @return int Number of suppression events processed */
    public function poll(string $integrationId): int
    {
        $integration = null;
        foreach ($this->integrations as $i) {
            if ($i->getId() === $integrationId) {
                $integration = $i;
                break;
            }
        }

        if (!$integration) {
            return 0;
        }

        $state = get_option(ImportSyncManager::STATE_KEY, []);
        $intState = $state[$integrationId] ?? [];
        $config = $intState['sync_settings'] ?? [];
        $cursor = $intState['stats']['poll_cursor'] ?? null;

        $result = $integration->pollSuppressions($config, $cursor);

        $events = $result['events'] ?? [];
        $newCursor = $result['cursor'] ?? $cursor;

        $emailToStatus = [];
        foreach ($events as $event) {
            $email = $event['email'] ?? '';
            $status = $event['status'] ?? '';
            if ($email !== '' && $status !== '') {
                $emailToStatus[$email] = $status;
            }
        }

        foreach ($emailToStatus as $email => $status) {
            $contact = $this->contactRepository->findByEmail($email);
            if (!$contact) {
                continue;
            }

            match ($status) {
                'unsubscribed' => $this->contactRepository->setChannelOptOut($contact['id'], 'email'),
                'bounced'      => $this->contactRepository->update($contact['id'], ['status' => 'bounced']),
                'complained'   => $this->contactRepository->update($contact['id'], ['status' => 'complained']),
                default        => null,
            };
        }

        $stats = $intState['stats'] ?? [];
        $stats['last_poll_at'] = gmdate('c');
        $stats['poll_cursor'] = $newCursor;

        $state[$integrationId]['stats'] = $stats;
        update_option(ImportSyncManager::STATE_KEY, $state, false);

        return count($events);
    }
}
