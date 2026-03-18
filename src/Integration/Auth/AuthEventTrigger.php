<?php

namespace WSms\Integration\Auth;

use WSms\Enums\EventType;
use WSms\Event\Events\AuthEvent;
use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class AuthEventTrigger extends AbstractTrigger
{
    /** @param EventType[] $eventTypes */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly array $payloadSchema,
        private readonly array $eventTypes,
        private readonly array $filterSchema = [],
        private readonly string $description = '',
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getGroup(): string
    {
        return 'Auth';
    }

    public function getPayloadSchema(): array
    {
        return $this->payloadSchema;
    }

    public function getFilterSchema(): array
    {
        return $this->filterSchema;
    }

    public function subscribe(callable $callback): void
    {
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
