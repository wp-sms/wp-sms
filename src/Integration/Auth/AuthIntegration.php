<?php

namespace WSms\Integration\Auth;

use WSms\Enums\EventType;
use WSms\Event\Events\AuthEvent;
use WSms\Flow\Contracts\TriggerInterface;
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
                    'user_id' => ['type' => 'integer', 'label' => __('User ID', 'wp-sms')],
                    'method'  => ['type' => 'string', 'label' => __('Login Method', 'wp-sms')],
                ],
                [EventType::LoginSuccess],
            ),
            new AuthEventTrigger(
                'auth.login_failure',
                __('Login Failure', 'wp-sms'),
                [
                    'identifier' => ['type' => 'string', 'label' => __('Identifier', 'wp-sms')],
                    'reason'     => ['type' => 'string', 'label' => __('Failure Reason', 'wp-sms')],
                ],
                [EventType::LoginFailure],
            ),
            new AuthEventTrigger(
                'auth.account_locked',
                __('Account Locked', 'wp-sms'),
                [
                    'user_id' => ['type' => 'integer', 'label' => __('User ID', 'wp-sms')],
                    'reason'  => ['type' => 'string', 'label' => __('Lock Reason', 'wp-sms')],
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

/**
 * A trigger that subscribes to AuthEvent via the EventDispatcher.
 */
class AuthEventTrigger implements TriggerInterface
{
    /** @param EventType[] $eventTypes */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly array $payloadSchema,
        private readonly array $eventTypes,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGroup(): string
    {
        return 'Auth';
    }

    public function getPayloadSchema(): array
    {
        return $this->payloadSchema;
    }

    public function subscribe(callable $callback): void
    {
        // Listen to the WordPress hook bridged by EventDispatcher
        add_action('wsms_auth_event', function (AuthEvent $event) use ($callback) {
            if (!in_array($event->eventType, $this->eventTypes, true)) {
                return;
            }

            $callback(array_merge(
                ['user_id' => $event->userId],
                $event->meta,
            ));
        });
    }
}
