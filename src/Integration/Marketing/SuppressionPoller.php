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

        $state = get_option('wsms_marketing_sync_state', []);
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
            if ($contact && $contact['status'] !== $status) {
                $this->contactRepository->update($contact['id'], ['status' => $status]);
            }
        }

        $stats = $intState['stats'] ?? [];
        $stats['last_poll_at'] = gmdate('c');
        $stats['poll_cursor'] = $newCursor;

        $state[$integrationId]['stats'] = $stats;
        update_option('wsms_marketing_sync_state', $state);

        return count($events);
    }
}
