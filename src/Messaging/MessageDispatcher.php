<?php

namespace WSms\Messaging;

use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\MessageFailedEvent;
use WSms\Event\Events\MessageSentEvent;
use WSms\Log\Contracts\MessageLoggerInterface;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\SendMessageJob;

defined('ABSPATH') || exit;

class MessageDispatcher
{
    public function __construct(
        private readonly GatewayRegistry $gatewayRegistry,
        private readonly MessageLoggerInterface $messageLogger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly QueueInterface $queue,
    ) {
    }

    /**
     * Send a message immediately (synchronous).
     *
     * @param string|null $gatewayId Specific gateway to use, or null for channel default
     */
    public function sendImmediate(MessageInterface $message, ?string $gatewayId = null): DeliveryResult
    {
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
            status: $result->success ? 'sent' : 'failed',
            executionId: $message->getFlowExecutionId(),
            subject: $meta['subject'] ?? null,
            providerId: $result->providerId,
            error: $result->error,
            cost: $result->cost,
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
        }

        return $result;
    }

    /**
     * Queue a message for async delivery. Used for flows and bulk sends.
     */
    public function sendQueued(MessageInterface $message): string
    {
        $gateway = $this->resolveGateway($message->getChannel());
        $gatewayId = $gateway ? $gateway->getId() : 'default';

        $logId = $this->messageLogger->logSend(
            gatewayId: $gatewayId,
            channel: $message->getChannel(),
            recipient: $message->getRecipient(),
            body: $message->getBody(),
            status: 'queued',
            executionId: $message->getFlowExecutionId(),
            subject: $message->getMeta()['subject'] ?? null,
        );

        $this->queue->dispatch(new SendMessageJob(
            gatewayId: $gatewayId,
            channel: $message->getChannel(),
            recipient: $message->getRecipient(),
            body: $message->getBody(),
            meta: $message->getMeta(),
            executionId: $message->getFlowExecutionId(),
        ));

        return $logId;
    }

    private function resolveGateway(string $channel): ?GatewayInterface
    {
        return $this->gatewayRegistry->getDefault($channel);
    }
}
