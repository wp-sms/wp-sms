<?php

namespace WSms\Tests\Support;

/**
 * Factory for creating test user objects.
 *
 * Replaces duplicated makeUser() helpers across test files.
 */
class UserFactory
{
    private static int $nextId = 1;

    /**
     * Create a basic user object.
     */
    public static function create(array $overrides = []): object
    {
        if (isset($overrides['ID'])) {
            $id = $overrides['ID'];
            self::$nextId = max(self::$nextId, $id + 1);
        } else {
            $id = self::$nextId++;
        }

        $user = new \stdClass();
        $user->ID = $id;
        $user->user_email = $overrides['user_email'] ?? 'user' . $id . '@example.com';
        $user->user_login = $overrides['user_login'] ?? 'user' . $id;
        $user->display_name = $overrides['display_name'] ?? 'User ' . $id;
        $user->first_name = $overrides['first_name'] ?? 'Test';
        $user->last_name = $overrides['last_name'] ?? 'User';
        $user->roles = $overrides['roles'] ?? ['subscriber'];
        $user->user_pass = $overrides['user_pass'] ?? '';
        $user->user_registered = $overrides['user_registered'] ?? '2024-01-01 00:00:00';

        return $user;
    }

    /**
     * Create a user with a phone number set in meta.
     */
    public static function withPhone(string $phone, array $overrides = []): object
    {
        $user = self::create($overrides);
        $GLOBALS['_test_user_meta'][$user->ID]['wsms_phone'] = $phone;

        return $user;
    }

    /**
     * Create a user with MFA enrolled.
     */
    public static function withMfa(string $channel = 'phone', array $overrides = []): object
    {
        $user = self::create($overrides);
        $GLOBALS['_test_user_meta'][$user->ID]['wsms_mfa_enabled'] = '1';

        if ($channel === 'phone') {
            $GLOBALS['_test_user_meta'][$user->ID]['wsms_phone'] = '+1234567890';
        }

        return $user;
    }

    /**
     * Create a user with email and phone verified.
     */
    public static function verified(array $overrides = []): object
    {
        $user = self::create($overrides);
        $GLOBALS['_test_user_meta'][$user->ID]['wsms_email_verified'] = '1';
        $GLOBALS['_test_user_meta'][$user->ID]['wsms_phone_verified'] = '1';

        return $user;
    }

    /**
     * Install a user into the global test stubs.
     *
     * Sets both $_test_userdata and $_test_get_user_by_result so that
     * get_userdata() and get_user_by() will resolve to this user.
     */
    public static function install(object $user): void
    {
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_get_user_by_result'] = $user;
        $GLOBALS['_test_get_users_result'] = [$user];
    }

    /**
     * Reset the factory state.
     */
    public static function reset(): void
    {
        self::$nextId = 1;
    }
}
