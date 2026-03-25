<?php

namespace WSms\Messaging;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Enums\ContactStatus;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;

defined('ABSPATH') || exit;

class SuppressionGuard
{
    private const SUPPRESSED_STATUSES = [ContactStatus::Bounced->value, ContactStatus::Complained->value];

    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
    ) {
    }

    public function check(MessageInterface $message): ?DeliveryResult
    {
        $recipient = $message->getRecipient();
        $channel = $message->getChannel();

        $contact = match (true) {
            in_array($channel, ['sms', 'whatsapp'], true) => $this->contacts->findByPhone($recipient),
            $channel === 'email' => $this->contacts->findByEmail($recipient),
            default => null,
        };

        if ($contact === null) {
            return null;
        }

        if (in_array($contact['status'], self::SUPPRESSED_STATUSES, true)) {
            return DeliveryResult::failed(
                sprintf('Recipient is %s', $contact['status'])
            );
        }

        return null;
    }
}
