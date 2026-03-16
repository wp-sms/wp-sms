<?php

namespace WSms\Contact;

use WSms\Contact\Contracts\ContactRepositoryInterface;

defined('ABSPATH') || exit;

class ContactSyncer
{
    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
    ) {
    }

    public function syncWpUser(int $userId): string
    {
        $user = get_userdata($userId);
        if (!$user) {
            return '';
        }

        $existing = $this->contacts->findByWpUser($userId);

        $data = [
            'email'      => $user->user_email,
            'first_name' => $user->first_name ?: null,
            'last_name'  => $user->last_name ?: null,
            'wp_user_id' => $userId,
            'source'     => 'sync',
        ];

        if ($existing) {
            $this->contacts->update($existing['id'], $data);
            return $existing['id'];
        }

        return $this->contacts->create($data);
    }

    public function syncAllWpUsers(int $batchSize = 100): int
    {
        $page = 1;
        $synced = 0;

        do {
            $users = get_users([
                'number' => $batchSize,
                'paged'  => $page,
                'fields' => ['ID'],
            ]);

            foreach ($users as $user) {
                $this->syncWpUser($user->ID);
                $synced++;
            }

            $page++;
        } while (count($users) === $batchSize);

        return $synced;
    }
}
