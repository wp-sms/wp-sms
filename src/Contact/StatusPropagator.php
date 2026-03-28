<?php

namespace WSms\Contact;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Enums\ContactStatus;
use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\ContactBouncedEvent;
use WSms\Event\Events\ContactComplainedEvent;
use WSms\Event\Events\ContactOptedOutEvent;
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
        if (!$update->permanent && !$update->complaint && !$update->unsubscribe) {
            return;
        }

        $contact = match ($channel) {
            'sms', 'whatsapp' => $this->contacts->findByPhone($recipient),
            'email' => $this->contacts->findByEmail($recipient),
            default => null,
        };

        if ($contact === null) {
            return;
        }

        if ($update->unsubscribe) {
            $this->contacts->setChannelOptOut($contact['id'], $channel);
            $this->eventDispatcher->dispatch(new ContactOptedOutEvent(
                contactIds: [$contact['id']],
                phone: $contact['phone'] ?? '',
                source: 'gateway_webhook',
                channel: $channel,
            ));
        } elseif ($update->complaint) {
            if ($contact['status'] !== ContactStatus::Complained->value) {
                $this->contacts->update($contact['id'], ['status' => ContactStatus::Complained->value]);
                $this->eventDispatcher->dispatch(new ContactComplainedEvent($contact['id'], $update->errorCode));
            }
        } else {
            // Permanent failure → bounced
            if ($contact['status'] === ContactStatus::Subscribed->value) {
                $this->contacts->update($contact['id'], ['status' => ContactStatus::Bounced->value]);
                $this->eventDispatcher->dispatch(new ContactBouncedEvent($contact['id'], $update->errorCode));
            }
        }
    }
}
