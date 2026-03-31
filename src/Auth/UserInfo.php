<?php

namespace WSms\Auth;

use WSms\Support\PhoneValidator;
use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

class UserInfo
{
    public const PLACEHOLDER_EMAIL_DOMAIN = 'noreply.wsms.local';
    public const PLACEHOLDER_USERNAME_PREFIX = 'wsms_';
    public const DEFAULT_PENDING_USER_TTL_HOURS = 24;

    public static function isPlaceholderEmail(string $email): bool
    {
        return str_ends_with($email, '@' . self::PLACEHOLDER_EMAIL_DOMAIN);
    }

    public static function isPlaceholderUsername(string $username): bool
    {
        return str_starts_with($username, self::PLACEHOLDER_USERNAME_PREFIX);
    }

    /**
     * Whether a user has a usable (known) password.
     *
     * '' = meta not set → pre-existing WP user, assume has password.
     * '1' = explicitly has password. '0' = explicitly no password (social login).
     */
    public static function hasUsablePassword(int $userId): bool
    {
        $meta = get_user_meta($userId, UserMeta::HAS_USABLE_PASSWORD, true);

        return $meta === '' || $meta === '1';
    }

    /**
     * Get the raw verification state for a user's email and phone channels.
     *
     * @return array{email: array{has: bool, verified: bool}, phone: array{has: bool, verified: bool}}
     */
    public static function getUserVerificationState(int $userId): array
    {
        $userEmail = get_userdata($userId)?->user_email ?? '';

        return [
            'email' => [
                'has'      => !empty($userEmail) && !self::isPlaceholderEmail($userEmail),
                'verified' => (bool) get_user_meta($userId, UserMeta::EMAIL_VERIFIED, true),
            ],
            'phone' => [
                'has'      => !empty(get_user_meta($userId, UserMeta::PHONE, true)),
                'verified' => (bool) get_user_meta($userId, UserMeta::PHONE_VERIFIED, true),
            ],
        ];
    }

    /**
     * Check if a phone number is already in use by another user.
     */
    public static function isPhoneTaken(string $phone, ?int $excludeUserId = null): bool
    {
        $normalized = PhoneValidator::toE164($phone);
        if ($normalized === null) {
            return false;
        }

        $args = [
            'meta_key'   => UserMeta::PHONE,
            'meta_value' => $normalized,
            'number'     => 1,
            'fields'     => 'ID',
        ];

        if ($excludeUserId !== null) {
            $args['exclude'] = [$excludeUserId];
        }

        return !empty(get_users($args));
    }
}
