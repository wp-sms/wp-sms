<?php

namespace WSms\Integration\Auth;

use WSms\Auth\RegistrationFormRepository;
use WSms\Enums\EventType;
use WSms\Integration\Auth\Triggers\FormRegistrationTrigger;
use WSms\Integration\Contracts\IntegrationInterface;

defined('ABSPATH') || exit;

class AuthIntegration implements IntegrationInterface
{
    public function __construct(
        private ?RegistrationFormRepository $formRepo = null,
    ) {
    }

    public function getId(): string
    {
        return 'wsms_auth';
    }

    public function getName(): string
    {
        return 'WSMS Auth';
    }

    public function getDescription(): string
    {
        return 'Security event triggers for logins, failures, and account lockouts.';
    }

    public function getCategory(): string
    {
        return 'security';
    }

    public function getIcon(): string
    {
        return 'shield';
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
        $triggers = [
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
                __('Fires when a user logs in successfully', 'wp-sms'),
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
                [],
                __('Fires when a login attempt fails', 'wp-sms'),
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
                [],
                __('Fires when an account is locked out', 'wp-sms'),
            ),
            new AuthEventTrigger(
                'auth.registration',
                __('User Registered', 'wp-sms'),
                [
                    'user_id' => [
                        'type' => 'integer',
                        'label' => __('User ID', 'wp-sms'),
                        'description' => __('The WordPress user ID of the new user', 'wp-sms'),
                        'example' => 42,
                    ],
                ],
                [EventType::Register, EventType::SocialRegistration],
                [],
                __('Fires when a new user registers (form or social)', 'wp-sms'),
            ),
            new AuthEventTrigger(
                'auth.password_reset',
                __('Password Changed', 'wp-sms'),
                [
                    'user_id' => [
                        'type' => 'integer',
                        'label' => __('User ID', 'wp-sms'),
                        'description' => __('The WordPress user ID whose password was changed', 'wp-sms'),
                        'example' => 42,
                    ],
                ],
                [EventType::PasswordResetComplete, EventType::PasswordChange],
                [],
                __('Fires when a user changes or resets their password', 'wp-sms'),
            ),
            new AuthEventTrigger(
                'auth.email_verified',
                __('Email Verified', 'wp-sms'),
                [
                    'user_id' => [
                        'type' => 'integer',
                        'label' => __('User ID', 'wp-sms'),
                        'description' => __('The WordPress user ID who verified their email', 'wp-sms'),
                        'example' => 42,
                    ],
                ],
                [EventType::EmailVerified],
                [],
                __('Fires when a user verifies their email address', 'wp-sms'),
            ),
            new AuthEventTrigger(
                'auth.phone_verified',
                __('Phone Verified', 'wp-sms'),
                [
                    'user_id' => [
                        'type' => 'integer',
                        'label' => __('User ID', 'wp-sms'),
                        'description' => __('The WordPress user ID who verified their phone', 'wp-sms'),
                        'example' => 42,
                    ],
                ],
                [EventType::PhoneVerified],
                [],
                __('Fires when a user verifies their phone number', 'wp-sms'),
            ),
        ];

        if ($this->formRepo) {
            $triggers[] = new FormRegistrationTrigger($this->formRepo);
        }

        return $triggers;
    }

    public function getActions(): array
    {
        return [];
    }

    public function getCapabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
    }

    public function connect(array $credentials): array
    {
        return $credentials;
    }

    public function disconnect(): void
    {
    }

    public function isConnected(): bool
    {
        return true;
    }
}
