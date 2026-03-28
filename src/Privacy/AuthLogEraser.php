<?php

namespace WSms\Privacy;

use WSms\Database\Connection;

defined('ABSPATH') || exit;

class AuthLogEraser
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * Erase auth logs for a user identified by email.
     * No exporter — auth logs contain IP/device info that shouldn't be re-exported.
     */
    public function erase(string $email, int $page): array
    {
        $user = get_user_by('email', $email);

        if (!$user) {
            return [
                'items_removed'  => false,
                'items_retained' => false,
                'messages'       => [],
                'done'           => true,
            ];
        }

        $removed = $this->db->delete(Connection::TABLE_AUTH_LOGS, ['user_id' => $user->ID]);

        return [
            'items_removed'  => $removed > 0,
            'items_retained' => false,
            'messages'       => [],
            'done'           => true,
        ];
    }
}
