<?php

namespace WSms\Integration\Contact\Actions;

use WSms\Contact\Contracts\ContactRepositoryInterface;

defined('ABSPATH') || exit;

trait ResolvesContactId
{
    abstract private function getContactRepository(): ContactRepositoryInterface;

    private function resolveContactId(array $config, array $payload): ?string
    {
        $contactId = $config['contact_id'] ?? '';
        if (!empty($contactId)) {
            return $contactId;
        }

        $userId = $payload['user_id'] ?? null;
        if ($userId) {
            $contact = $this->getContactRepository()->findByWpUser((int) $userId);
            return $contact['id'] ?? null;
        }

        return $payload['contact_id'] ?? null;
    }
}
