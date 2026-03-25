<?php

namespace WSms\Contact;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Enums\ContactStatus;
use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\ContactBouncedEvent;
use WSms\Event\Events\ContactComplainedEvent;
use WSms\Messaging\Contracts\StatusUpdate;

defined('ABSPATH') || exit;

class StatusPropagator
{
    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function propagate(StatusUpdate $update, string $recipient, string $channel): void
    {
        if (!$update->permanent) {
            return;
        }

        $contact = match ($channel) {
            'sms', 'whatsapp' => $this->contacts->findByPhone($recipient),
            'email' => $this->contacts->findByEmail($recipient),
            default => null,
        };

        if ($contact === null || $contact['status'] !== ContactStatus::Subscribed->value) {
            return;
        }

        $newStatus = $update->complaint
            ? ContactStatus::Complained
            : ContactStatus::Bounced;

        $this->contacts->update($contact['id'], ['status' => $newStatus->value]);

        $event = $update->complaint
            ? new ContactComplainedEvent($contact['id'], $update->errorCode)
            : new ContactBouncedEvent($contact['id'], $update->errorCode);

        $this->eventDispatcher->dispatch($event);
    }
}
