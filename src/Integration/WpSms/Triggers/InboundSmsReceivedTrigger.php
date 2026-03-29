<?php

namespace WSms\Integration\WpSms\Triggers;

use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\InboundSmsReceivedEvent;
use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class InboundSmsReceivedTrigger extends AbstractTrigger
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getId(): string
    {
        return 'wsms.inbound_sms_received';
    }

    public function getName(): string
    {
        return __('Inbound SMS Received', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when an SMS is received from a phone number (excluding STOP/START/HELP keywords)', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('WSMS', 'wp-sms');
    }

    public function getPayloadSchema(): array
    {
        return [
            'from' => [
                'type'        => 'string',
                'label'       => __('From Number', 'wp-sms'),
                'description' => __('The sender phone number', 'wp-sms'),
                'example'     => '+15551234567',
            ],
            'to' => [
                'type'        => 'string',
                'label'       => __('To Number', 'wp-sms'),
                'description' => __('The receiving number (your gateway number)', 'wp-sms'),
                'example'     => '+15559876543',
            ],
            'body' => [
                'type'        => 'string',
                'label'       => __('Message Body', 'wp-sms'),
                'description' => __('The text content of the inbound message', 'wp-sms'),
                'example'     => 'Hello, I have a question about my order.',
            ],
            'gateway_id' => [
                'type'        => 'string',
                'label'       => __('Gateway ID', 'wp-sms'),
                'description' => __('The gateway that received the message', 'wp-sms'),
                'example'     => 'twilio',
            ],
            'provider_id' => [
                'type'        => 'string',
                'label'       => __('Provider Message ID', 'wp-sms'),
                'description' => __('The message ID from the gateway provider', 'wp-sms'),
                'example'     => 'SM1234567890abcdef',
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        $this->eventDispatcher->listen(InboundSmsReceivedEvent::class, function (InboundSmsReceivedEvent $event) use ($callback) {
            $callback([
                'from'        => $event->from,
                'to'          => $event->to,
                'body'        => $event->body,
                'gateway_id'  => $event->gatewayId,
                'provider_id' => $event->providerId,
            ]);
        });
    }
}
