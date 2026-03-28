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

    private const OPT_OUT_CHANNELS = ['sms', 'email', 'whatsapp', 'telegram', 'line', 'rcs'];

    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
    ) {
    }

    public function check(MessageInterface $message): ?DeliveryResult
    {
        $channel = $message->getChannel();

        // Webhook is system-to-system — skip all contact-level checks
        if (!in_array($channel, self::OPT_OUT_CHANNELS, true)) {
            return null;
        }

        $recipient = $message->getRecipient();

        $contact = match (true) {
            in_array($channel, ['sms', 'whatsapp'], true) => $this->contacts->findByPhone($recipient),
            $channel === 'email' => $this->contacts->findByEmail($recipient),
            default => null,
        };

        if ($contact === null) {
            return null;
        }

        // Global: bounced/complained → block all user-facing channels, all message types
        if (in_array($contact['status'], self::SUPPRESSED_STATUSES, true)) {
            return DeliveryResult::failed(
                sprintf('Recipient is %s', $contact['status'])
            );
        }

        // Per-channel opt-out → block campaign messages only
        // Transactional messages (no campaignId) bypass this check
        if ($message->getCampaignId() !== null) {
            $optOuts = $contact['channel_opt_outs'] ?? [];

            if (!empty($optOuts[$channel])) {
                return DeliveryResult::failed(
                    sprintf('Recipient opted out of %s', $channel)
                );
            }
        }

        return null;
    }
}
