<?php

namespace WSms\Integration\Auth;

use WSms\Enums\EventType;
use WSms\Integration\Contracts\IntegrationInterface;

defined('ABSPATH') || exit;

class AuthIntegration implements IntegrationInterface
{
    public function getId(): string
    {
        return 'wsms_auth';
    }

    public function getName(): string
    {
        return 'WSMS Auth';
    }

    public function getCategory(): string
    {
        return 'security';
    }

    public function getIcon(): string
    {
        return 'dashicons-shield';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getAuthType(): string
    {
        return 'none';
    }

    public function getAuthSchema(): array
    {
        return [];
    }

    public function getTriggers(): array
    {
        return [
            new AuthEventTrigger(
                'auth.login_success',
                __('Login Success', 'wp-sms'),
                [
                    'user_id' => [
                        'type' => 'integer',
                        'label' => __('User ID', 'wp-sms'),
                        'description' => __('The WordPress user ID who logged in', 'wp-sms'),
                        'example' => 42,
                    ],
                    'method' => [
                        'type' => 'string',
                        'label' => __('Login Method', 'wp-sms'),
                        'description' => __('Authentication method used (password, otp, magic_link)', 'wp-sms'),
                        'example' => 'password',
                    ],
                ],
                [EventType::LoginSuccess],
                [
                    'method' => [
                        'type'        => 'string',
                        'label'       => __('Login Method', 'wp-sms'),
                        'description' => __('Only trigger for this login method', 'wp-sms'),
                        'enum'        => ['password', 'otp', 'magic_link'],
                    ],
                ],
            ),
            new AuthEventTrigger(
                'auth.login_failure',
                __('Login Failure', 'wp-sms'),
                [
                    'identifier' => [
                        'type' => 'string',
                        'label' => __('Identifier', 'wp-sms'),
                        'description' => __('The login identifier that was attempted', 'wp-sms'),
                        'example' => 'user@example.com',
                    ],
                    'reason' => [
                        'type' => 'string',
                        'label' => __('Failure Reason', 'wp-sms'),
                        'description' => __('Why the login attempt failed', 'wp-sms'),
                        'example' => 'invalid_password',
                    ],
                ],
                [EventType::LoginFailure],
            ),
            new AuthEventTrigger(
                'auth.account_locked',
                __('Account Locked', 'wp-sms'),
                [
                    'user_id' => [
                        'type' => 'integer',
                        'label' => __('User ID', 'wp-sms'),
                        'description' => __('The WordPress user ID that was locked', 'wp-sms'),
                        'example' => 42,
                    ],
                    'reason' => [
                        'type' => 'string',
                        'label' => __('Lock Reason', 'wp-sms'),
                        'description' => __('Why the account was locked', 'wp-sms'),
                        'example' => 'too_many_failures',
                    ],
                ],
                [EventType::AccountLocked],
            ),
        ];
    }

    public function getActions(): array
    {
        return [];
    }

    public function boot(): void
    {
    }
}
