<?php

namespace WSms\Integration\WpSms\Triggers;

use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\ContactOptedOutEvent;
use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class ContactOptedOutTrigger extends AbstractTrigger
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getId(): string
    {
        return 'wsms.contact_opted_out';
    }

    public function getName(): string
    {
        return __('Contact Opted Out', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a contact opts out of a channel', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WSMS';
    }

    public function getPayloadSchema(): array
    {
        return [
            'phone' => [
                'type'        => 'string',
                'label'       => __('Phone Number', 'wp-sms'),
                'description' => __('The phone number that opted out', 'wp-sms'),
                'example'     => '+15551234567',
            ],
            'source' => [
                'type'        => 'string',
                'label'       => __('Source', 'wp-sms'),
                'description' => __('How the opt-out occurred (sms_keyword, gateway, admin, api)', 'wp-sms'),
                'example'     => 'sms_keyword',
            ],
            'keyword' => [
                'type'        => 'string',
                'label'       => __('Keyword', 'wp-sms'),
                'description' => __('The keyword used to opt out, if any', 'wp-sms'),
                'example'     => 'stop',
            ],
            'gateway_id' => [
                'type'        => 'string',
                'label'       => __('Gateway ID', 'wp-sms'),
                'description' => __('The gateway that received the opt-out', 'wp-sms'),
                'example'     => 'twilio',
            ],
            'contact_ids' => [
                'type'        => 'array',
                'label'       => __('Contact IDs', 'wp-sms'),
                'description' => __('IDs of contacts that opted out', 'wp-sms'),
                'example'     => ['01HY...'],
            ],
            'channel' => [
                'type'        => 'string',
                'label'       => __('Channel', 'wp-sms'),
                'description' => __('The channel the contact opted out of (e.g. sms, email)', 'wp-sms'),
                'example'     => 'sms',
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        $this->eventDispatcher->listen(ContactOptedOutEvent::class, function (ContactOptedOutEvent $event) use ($callback) {
            $callback([
                'phone'       => $event->phone,
                'source'      => $event->source,
                'keyword'     => $event->keyword,
                'gateway_id'  => $event->gatewayId,
                'contact_ids' => $event->contactIds,
                'channel'     => $event->channel,
            ]);
        });
    }
}
