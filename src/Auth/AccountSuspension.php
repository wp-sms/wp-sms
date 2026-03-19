<?php

namespace WSms\Auth;

use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

class AccountSuspension
{

    public const NOT_SUSPENDED = ['suspended' => false, 'at' => null, 'by' => null];
    public static function errorMessage(): string
    {
        return __('Your account has been suspended. Contact an administrator.', 'wp-sms');
    }

    public function suspend(int $userId, int $adminId): void
    {
        update_user_meta($userId, UserMeta::SUSPENDED, wp_json_encode([
            'at' => gmdate('Y-m-d\TH:i:s\Z'),
            'by' => $adminId,
        ]));

        \WP_Session_Tokens::get_instance($userId)->destroy_all();
    }

    public function unsuspend(int $userId): void
    {
        delete_user_meta($userId, UserMeta::SUSPENDED);
    }

    /**
     * @return array{suspended: bool, at: ?string, by: ?int}
     */
    public function isSuspended(int $userId): array
    {
        $raw = get_user_meta($userId, UserMeta::SUSPENDED, true);

        if (empty($raw)) {
            return self::NOT_SUSPENDED;
        }

        $data = json_decode($raw, true);

        return [
            'suspended' => true,
            'at'        => $data['at'] ?? null,
            'by'        => isset($data['by']) ? (int) $data['by'] : null,
        ];
    }
}
