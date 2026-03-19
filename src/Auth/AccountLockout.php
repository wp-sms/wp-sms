<?php

namespace WSms\Auth;

use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

class AccountLockout
{
    private const DEFAULT_THRESHOLDS = [
        5  => 300,   // 5 min
        10 => 900,   // 15 min
        15 => 3600,  // 60 min
    ];

    public function __construct(
        private SettingsRepository $settingsRepo,
    ) {
    }

    public function recordFailure(int $userId): void
    {
        $attempts = (int) get_user_meta($userId, UserMeta::FAILED_ATTEMPTS, true);
        $attempts++;
        update_user_meta($userId, UserMeta::FAILED_ATTEMPTS, $attempts);

        $thresholds = $this->getThresholds();

        if (isset($thresholds[$attempts])) {
            update_user_meta($userId, UserMeta::LOCKOUT_UNTIL, time() + $thresholds[$attempts]);
        }
    }

    /**
     * @return array{locked: bool, until: ?string, attempts: int}
     */
    public function isLocked(int $userId): array
    {
        $attempts = (int) get_user_meta($userId, UserMeta::FAILED_ATTEMPTS, true);
        $lockoutUntil = (int) get_user_meta($userId, UserMeta::LOCKOUT_UNTIL, true);

        if ($lockoutUntil > 0 && $lockoutUntil > time()) {
            return [
                'locked'   => true,
                'until'    => gmdate('Y-m-d\TH:i:s\Z', $lockoutUntil),
                'attempts' => $attempts,
            ];
        }

        // Auto-clear expired lockout.
        if ($lockoutUntil > 0) {
            delete_user_meta($userId, UserMeta::LOCKOUT_UNTIL);
        }

        return [
            'locked'   => false,
            'until'    => null,
            'attempts' => $attempts,
        ];
    }

    public function reset(int $userId): void
    {
        delete_user_meta($userId, UserMeta::FAILED_ATTEMPTS);
        delete_user_meta($userId, UserMeta::LOCKOUT_UNTIL);
    }

    private function getThresholds(): array
    {
        return $this->settingsRepo->get('lockout_thresholds', self::DEFAULT_THRESHOLDS);
    }
}
