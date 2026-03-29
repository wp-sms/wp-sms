<?php

namespace WSms\Messaging\Inbound;

use WSms\Contact\ContactRepository;
use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\ContactOptedInEvent;
use WSms\Event\Events\ContactOptedOutEvent;
use WSms\Event\Events\InboundSmsReceivedEvent;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Message\Message;
use WSms\Messaging\MessageDispatcher;

defined('ABSPATH') || exit;

class OptOutManager
{
    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MessageDispatcher $messageDispatcher,
        private readonly KeywordMatcher $keywordMatcher,
    ) {
    }

    /**
     * Process an inbound message — detect opt-out/opt-in/help keywords or dispatch as generic inbound.
     *
     * @return 'stop'|'start'|'help'|null The matched action, or null for no keyword match
     */
    public function processInboundMessage(InboundMessage $message, string $gatewayId): ?string
    {
        // If the gateway already parsed the opt-out type, use that verdict
        if ($message->optOutType !== null) {
            $action = match (strtolower($message->optOutType)) {
                'stop'  => 'stop',
                'start' => 'start',
                'help'  => 'help',
                default => null,
            };
        } else {
            $action = $this->keywordMatcher->match($message->body);
        }

        if ($action === null) {
            $this->eventDispatcher->dispatch(new InboundSmsReceivedEvent(
                from: $message->from,
                to: $message->to,
                body: $message->body,
                gatewayId: $gatewayId,
                providerId: $message->providerId,
            ));

            return null;
        }

        return match ($action) {
            'stop'  => $this->handleStatusChange($message->from, 'stop', $gatewayId, 'sms_keyword', $action),
            'start' => $this->handleStatusChange($message->from, 'start', $gatewayId, 'sms_keyword', $action),
            'help'  => $this->handleHelp($message->from, $gatewayId),
        };
    }

    public function processGatewayOptOut(string $phone, string $gatewayId): void
    {
        $this->handleStatusChange($phone, 'stop', $gatewayId, 'gateway');
    }

    public function processGatewayOptIn(string $phone, string $gatewayId): void
    {
        $this->handleStatusChange($phone, 'start', $gatewayId, 'gateway');
    }

    public function optOut(string $phone): void
    {
        $this->handleStatusChange($phone, 'stop', null, 'admin');
    }

    public function optIn(string $phone): void
    {
        $this->handleStatusChange($phone, 'start', null, 'admin');
    }

    /**
     * @return 'stop'|'start'
     */
    private function handleStatusChange(
        string $phone,
        string $action,
        ?string $gatewayId,
        string $source,
        ?string $keyword = null,
    ): string {
        $phone = ContactRepository::normalizePhone($phone);

        $contacts = $this->contacts->findAllByPhone($phone);

        if (empty($contacts)) {
            $createData = [
                'phone'  => $phone,
                'status' => 'subscribed',
                'source' => 'sms_optout',
            ];
            if ($action === 'stop') {
                $createData['channel_opt_outs'] = ['sms' => current_time('mysql')];
            }
            $id = $this->contacts->create($createData);
            $changedIds = [$id];
        } else {
            $changedIds = [];
            foreach ($contacts as $contact) {
                $isOptedOut = !empty(($contact['channel_opt_outs'] ?? [])['sms']);

                if ($action === 'stop') {
                    if ($isOptedOut) {
                        continue;
                    }
                    $this->contacts->setChannelOptOut($contact['id'], 'sms');
                } else {
                    if (!$isOptedOut) {
                        continue;
                    }
                    $this->contacts->clearChannelOptOut($contact['id'], 'sms');
                }
                $changedIds[] = $contact['id'];
            }
        }

        if (!empty($changedIds)) {
            $event = $action === 'stop'
                ? new ContactOptedOutEvent(
                    contactIds: $changedIds,
                    phone: $phone,
                    source: $source,
                    gatewayId: $gatewayId,
                    keyword: $keyword,
                    channel: 'sms',
                )
                : new ContactOptedInEvent(
                    contactIds: $changedIds,
                    phone: $phone,
                    source: $source,
                    gatewayId: $gatewayId,
                    keyword: $keyword,
                    channel: 'sms',
                );

            $this->eventDispatcher->dispatch($event);
        }

        // Send auto-reply if enabled (always reply even if idempotent, per CTIA)
        if ($gatewayId !== null) {
            $this->sendAutoReply($phone, $action, $gatewayId);
        }

        return $action;
    }

    /**
     * @return 'help'
     */
    private function handleHelp(string $phone, string $gatewayId): string
    {
        $this->sendAutoReply(ContactRepository::normalizePhone($phone), 'help', $gatewayId);

        return 'help';
    }

    private function sendAutoReply(string $phone, string $action, string $gatewayId): void
    {
        $settings = get_option('wsms_optout_settings', []);

        $enabledKey = "auto_reply_{$action}_enabled";
        $textKey = "auto_reply_{$action}_text";

        $defaults = self::getDefaults();
        $enabled = $settings[$enabledKey] ?? $defaults[$enabledKey] ?? false;
        $text = $settings[$textKey] ?? $defaults[$textKey] ?? '';

        if (!$enabled || empty($text)) {
            return;
        }

        $this->messageDispatcher->sendImmediate(
            new Message(channel: 'sms', recipient: $phone, body: $text),
            $gatewayId,
        );
    }

    public static function getDefaults(): array
    {
        return [
            'auto_reply_stop_enabled'       => true,
            'auto_reply_stop_text'          => __('You have been unsubscribed. Reply START to re-subscribe.', 'wp-sms'),
            'auto_reply_start_enabled'      => true,
            'auto_reply_start_text'         => __('You have been re-subscribed. Reply STOP to unsubscribe.', 'wp-sms'),
            'auto_reply_help_enabled'       => true,
            'auto_reply_help_text'          => __('Reply STOP to unsubscribe. Reply START to re-subscribe.', 'wp-sms'),
            'custom_stop_keywords'          => [],
            'custom_start_keywords'         => [],
            'default_campaign_opt_out_text' => __('Reply STOP to unsubscribe', 'wp-sms'),
        ];
    }
}
