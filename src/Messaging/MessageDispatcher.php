<?php

namespace WSms\Messaging;

use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\MessageFailedEvent;
use WSms\Event\Events\MessageSentEvent;
use WSms\Log\Contracts\MessageLoggerInterface;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\Inbound\OptOutManager;
use WSms\PhoneRestriction\SendingPolicyGuard;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\SendMessageJob;

defined('ABSPATH') || exit;

class MessageDispatcher
{
    /** @var (\Closure(): OptOutManager)|null */
    private ?\Closure $optOutManagerResolver = null;

    /** @var (\Closure(): SendingPolicyGuard)|null */
    private ?\Closure $sendingPolicyGuardResolver = null;

    public function __construct(
        private readonly GatewayRegistry $gatewayRegistry,
        private readonly MessageLoggerInterface $messageLogger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly QueueInterface $queue,
    ) {
    }

    /** @param \Closure(): OptOutManager $resolver */
    public function setOptOutManagerResolver(\Closure $resolver): void
    {
        $this->optOutManagerResolver = $resolver;
    }

    /** @param \Closure(): SendingPolicyGuard $resolver */
    public function setSendingPolicyGuardResolver(\Closure $resolver): void
    {
        $this->sendingPolicyGuardResolver = $resolver;
    }

    /**
     * Send a message immediately (synchronous).
     *
     * @param string|null $gatewayId Specific gateway to use, or null for channel default
     */
    public function sendImmediate(MessageInterface $message, ?string $gatewayId = null): DeliveryResult
    {
        $restriction = $this->checkPhoneRestriction($message);

        if ($restriction !== null) {
            return $restriction;
        }

        $gateway = $gatewayId
            ? $this->gatewayRegistry->get($gatewayId)
            : $this->resolveGateway($message->getChannel());

        if ($gateway === null) {
            return DeliveryResult::failed(
                sprintf(__('No gateway configured for channel: %s', 'wp-sms'), $message->getChannel())
            );
        }

        $result = $gateway->send($message);

        $meta = $message->getMeta();

        $logId = $this->messageLogger->logSend(
            gatewayId: $gateway->getId(),
            channel: $message->getChannel(),
            recipient: $message->getRecipient(),
            body: $message->getBody(),
            status: $result->success ? $result->status : 'failed',
            executionId: $message->getFlowExecutionId(),
            subject: $meta['subject'] ?? null,
            providerId: $result->providerId,
            error: $result->error,
            cost: $result->cost,
            type: $message->getCampaignId() ? 'campaign' : 'transactional',
            campaignId: $message->getCampaignId(),
        );

        if ($result->success) {
            $this->eventDispatcher->dispatch(new MessageSentEvent(
                messageId: $logId,
                channel: $message->getChannel(),
                recipient: $message->getRecipient(),
                gatewayId: $gateway->getId(),
                result: $result,
                executionId: $message->getFlowExecutionId(),
            ));
        } else {
            $this->eventDispatcher->dispatch(new MessageFailedEvent(
                channel: $message->getChannel(),
                recipient: $message->getRecipient(),
                gatewayId: $gateway->getId(),
                error: $result->error ?? __('Unknown error', 'wp-sms'),
                executionId: $message->getFlowExecutionId(),
            ));

            // Detect gateway-level opt-out from send errors (e.g., Twilio 21610)
            if ($this->optOutManagerResolver !== null
                && $gateway instanceof SupportsOptOutDetection
                && $gateway->isOptOutError($result)
            ) {
                ($this->optOutManagerResolver)()->processGatewayOptOut($message->getRecipient(), $gateway->getId());
            }
        }

        return $result;
    }

    /**
     * Queue a message for async delivery. Used for flows and bulk sends.
     *
     * @param string|null $gatewayId Specific gateway to use, or null for channel default
     */
    public function sendQueued(MessageInterface $message, ?string $gatewayId = null): string
    {
        $restriction = $this->checkPhoneRestriction($message);

        if ($restriction !== null) {
            return $this->messageLogger->logSend(
                gatewayId: 'none',
                channel: $message->getChannel(),
                recipient: $message->getRecipient(),
                body: $message->getBody(),
                status: 'blocked',
                executionId: $message->getFlowExecutionId(),
                subject: $message->getMeta()['subject'] ?? null,
                error: $restriction->error,
                type: $message->getCampaignId() ? 'campaign' : 'transactional',
                campaignId: $message->getCampaignId(),
            );
        }

        $gateway = $gatewayId
            ? $this->gatewayRegistry->get($gatewayId)
            : $this->resolveGateway($message->getChannel());
        $resolvedGatewayId = $gateway ? $gateway->getId() : 'default';

        $logId = $this->messageLogger->logSend(
            gatewayId: $resolvedGatewayId,
            channel: $message->getChannel(),
            recipient: $message->getRecipient(),
            body: $message->getBody(),
            status: 'queued',
            executionId: $message->getFlowExecutionId(),
            subject: $message->getMeta()['subject'] ?? null,
            type: $message->getCampaignId() ? 'campaign' : 'transactional',
            campaignId: $message->getCampaignId(),
        );

        $this->queue->dispatch(new SendMessageJob(
            gatewayId: $resolvedGatewayId,
            channel: $message->getChannel(),
            recipient: $message->getRecipient(),
            body: $message->getBody(),
            meta: $message->getMeta(),
            executionId: $message->getFlowExecutionId(),
            logId: $logId,
        ));

        return $logId;
    }

    private function checkPhoneRestriction(MessageInterface $message): ?DeliveryResult
    {
        if ($this->sendingPolicyGuardResolver === null) {
            return null;
        }

        $channel = $message->getChannel();

        if (!in_array($channel, ['sms', 'whatsapp'], true)) {
            return null;
        }

        $result = ($this->sendingPolicyGuardResolver)()->check($message->getRecipient(), 'messaging');

        if ($result->allowed) {
            return null;
        }

        return DeliveryResult::failed($result->message ?? 'Phone number restricted');
    }

    private function resolveGateway(string $channel): ?GatewayInterface
    {
        return $this->gatewayRegistry->getDefault($channel);
    }
}
