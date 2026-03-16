<?php

namespace WSms\Auth;

defined('ABSPATH') || exit;

class AccountSuspension
{
    private const META_KEY = 'wsms_suspended';

    public const NOT_SUSPENDED = ['suspended' => false, 'at' => null, 'by' => null];
    public static function errorMessage(): string
    {
        return __('Your account has been suspended. Contact an administrator.', 'wp-sms');
    }

    public function suspend(int $userId, int $adminId): void
    {
        update_user_meta($userId, self::META_KEY, wp_json_encode([
            'at' => gmdate('Y-m-d\TH:i:s\Z'),
            'by' => $adminId,
        ]));

        \WP_Session_Tokens::get_instance($userId)->destroy_all();
    }

    public function unsuspend(int $userId): void
    {
        delete_user_meta($userId, self::META_KEY);
    }

    /**
     * @return array{suspended: bool, at: ?string, by: ?int}
     */
    public function isSuspended(int $userId): array
    {
        $raw = get_user_meta($userId, self::META_KEY, true);

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
