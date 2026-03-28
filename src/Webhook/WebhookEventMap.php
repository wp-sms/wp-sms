<?php

namespace WSms\Webhook;

use WSms\Event\Events\CampaignCancelledEvent;
use WSms\Event\Events\CampaignCompletedEvent;
use WSms\Event\Events\CampaignStartedEvent;
use WSms\Event\Events\ContactBouncedEvent;
use WSms\Event\Events\ContactComplainedEvent;
use WSms\Event\Events\ContactOptedInEvent;
use WSms\Event\Events\ContactOptedOutEvent;
use WSms\Event\Events\FlowCompletedEvent;
use WSms\Event\Events\FlowStartedEvent;
use WSms\Event\Events\InboundSmsReceivedEvent;
use WSms\Event\Events\MessageFailedEvent;
use WSms\Event\Events\MessageSentEvent;

defined('ABSPATH') || exit;

class WebhookEventMap
{
    private const EVENT_CLASS_MAP = [
        MessageSentEvent::class       => 'message.sent',
        MessageFailedEvent::class     => 'message.failed',
        ContactBouncedEvent::class    => 'contact.bounced',
        ContactComplainedEvent::class => 'contact.complained',
        ContactOptedInEvent::class    => 'contact.opted_in',
        ContactOptedOutEvent::class   => 'contact.opted_out',
        InboundSmsReceivedEvent::class => 'sms.received',
        CampaignStartedEvent::class   => 'campaign.started',
        CampaignCompletedEvent::class => 'campaign.completed',
        CampaignCancelledEvent::class => 'campaign.cancelled',
        FlowStartedEvent::class       => 'flow.started',
        FlowCompletedEvent::class     => 'flow.completed',
    ];

    private const HOOK_EVENTS = [
        'contact.created',
        'contact.updated',
        'subscription.confirmed',
    ];

    private static function eventMeta(): array
    {
        return [
            'message.sent' => [
                'group'       => __('Messages', 'wp-sms'),
                'label'       => __('Message Sent', 'wp-sms'),
                'description' => __('Fires when a message is delivered successfully.', 'wp-sms'),
            ],
            'message.failed' => [
                'group'       => __('Messages', 'wp-sms'),
                'label'       => __('Message Failed', 'wp-sms'),
                'description' => __('Fires when a message fails to send.', 'wp-sms'),
            ],
            'contact.created' => [
                'group'       => __('Contacts', 'wp-sms'),
                'label'       => __('Contact Created', 'wp-sms'),
                'description' => __('Fires when a new contact is added.', 'wp-sms'),
            ],
            'contact.updated' => [
                'group'       => __('Contacts', 'wp-sms'),
                'label'       => __('Contact Updated', 'wp-sms'),
                'description' => __('Fires when a contact\'s details change.', 'wp-sms'),
            ],
            'contact.opted_in' => [
                'group'       => __('Contacts', 'wp-sms'),
                'label'       => __('Contact Opted In', 'wp-sms'),
                'description' => __('Fires when a contact subscribes or re-subscribes.', 'wp-sms'),
            ],
            'contact.opted_out' => [
                'group'       => __('Contacts', 'wp-sms'),
                'label'       => __('Contact Opted Out', 'wp-sms'),
                'description' => __('Fires when a contact unsubscribes.', 'wp-sms'),
            ],
            'contact.bounced' => [
                'group'       => __('Contacts', 'wp-sms'),
                'label'       => __('Contact Bounced', 'wp-sms'),
                'description' => __('Fires when a contact\'s message bounces.', 'wp-sms'),
            ],
            'contact.complained' => [
                'group'       => __('Contacts', 'wp-sms'),
                'label'       => __('Contact Complained', 'wp-sms'),
                'description' => __('Fires when a contact marks a message as spam.', 'wp-sms'),
            ],
            'sms.received' => [
                'group'       => __('Inbound', 'wp-sms'),
                'label'       => __('SMS Received', 'wp-sms'),
                'description' => __('Fires when an inbound SMS is received.', 'wp-sms'),
            ],
            'campaign.started' => [
                'group'       => __('Campaigns', 'wp-sms'),
                'label'       => __('Campaign Started', 'wp-sms'),
                'description' => __('Fires when a campaign begins sending.', 'wp-sms'),
            ],
            'campaign.completed' => [
                'group'       => __('Campaigns', 'wp-sms'),
                'label'       => __('Campaign Completed', 'wp-sms'),
                'description' => __('Fires when a campaign finishes. Use this instead of message.sent for campaign summaries.', 'wp-sms'),
            ],
            'campaign.cancelled' => [
                'group'       => __('Campaigns', 'wp-sms'),
                'label'       => __('Campaign Cancelled', 'wp-sms'),
                'description' => __('Fires when a campaign is cancelled.', 'wp-sms'),
            ],
            'flow.started' => [
                'group'       => __('Flows', 'wp-sms'),
                'label'       => __('Flow Started', 'wp-sms'),
                'description' => __('Fires when a flow execution begins.', 'wp-sms'),
            ],
            'flow.completed' => [
                'group'       => __('Flows', 'wp-sms'),
                'label'       => __('Flow Completed', 'wp-sms'),
                'description' => __('Fires when a flow execution finishes.', 'wp-sms'),
            ],
            'subscription.confirmed' => [
                'group'       => __('Subscribers', 'wp-sms'),
                'label'       => __('Subscription Confirmed', 'wp-sms'),
                'description' => __('Fires when a subscription form submission is confirmed.', 'wp-sms'),
            ],
        ];
    }

    public static function getEventName(string $eventClass): ?string
    {
        return self::EVENT_CLASS_MAP[$eventClass] ?? null;
    }

    /** @return string[] All available event names (typed + hook-based). */
    public static function getAllEventNames(): array
    {
        return array_merge(array_values(self::EVENT_CLASS_MAP), self::HOOK_EVENTS);
    }

    /** @return string[] Event class names that are dispatched via EventDispatcher (not WP hooks). */
    public static function getTypedEventClasses(): array
    {
        return array_keys(self::EVENT_CLASS_MAP);
    }

    /**
     * Grouped events for the UI event picker.
     *
     * @return array<string, list<array{name: string, label: string, description: string, sample_payload: array}>>
     */
    public static function getGroupedEvents(): array
    {
        $groups = [];

        foreach (self::eventMeta() as $name => $meta) {
            $groups[$meta['group']][] = [
                'name'           => $name,
                'label'          => $meta['label'],
                'description'    => $meta['description'],
                'sample_payload' => self::getSamplePayload($name),
            ];
        }

        return $groups;
    }

    public static function serializePayload(string $eventName, object $event): array
    {
        return match ($eventName) {
            'message.sent'        => self::serializeMessageSent($event),
            'message.failed'      => self::serializeMessageFailed($event),
            'contact.opted_in'    => self::serializeContactOptedIn($event),
            'contact.opted_out'   => self::serializeContactOptedOut($event),
            'contact.bounced',
            'contact.complained'  => self::serializeContactIssue($event),
            'sms.received'        => self::serializeInboundSms($event),
            'campaign.started'    => self::serializeCampaignStarted($event),
            'campaign.completed'  => self::serializeCampaignCompleted($event),
            'campaign.cancelled'  => self::serializeCampaignCancelled($event),
            'flow.started'        => self::serializeFlowStarted($event),
            'flow.completed'      => self::serializeFlowCompleted($event),
            default               => [],
        };
    }

    public static function serializeHookPayload(string $eventName, array $args): array
    {
        return match ($eventName) {
            'contact.created'          => self::serializeContactCreated($args),
            'contact.updated'          => self::serializeContactUpdated($args),
            'subscription.confirmed'   => self::serializeSubscriptionConfirmed($args),
            default                    => [],
        };
    }

    public static function getSamplePayload(string $eventName): array
    {
        return match ($eventName) {
            'message.sent' => [
                'message_id'  => '01JEXAMPLE123456789',
                'channel'     => 'sms',
                'recipient'   => '+15551234567',
                'gateway'     => 'twilio',
                'status'      => 'sent',
                'provider_id' => 'SM1234567890abcdef',
            ],
            'message.failed' => [
                'channel'   => 'sms',
                'recipient' => '+15551234567',
                'gateway'   => 'twilio',
                'error'     => 'Invalid phone number format',
            ],
            'contact.created' => [
                'contact_id' => '01JEXAMPLE123456789',
                'email'      => 'jane@example.com',
                'phone'      => '+15551234567',
                'first_name' => 'Jane',
                'last_name'  => 'Doe',
                'source'     => 'manual',
            ],
            'contact.updated' => [
                'contact_id'     => '01JEXAMPLE123456789',
                'email'          => 'jane@example.com',
                'phone'          => '+15551234567',
                'first_name'     => 'Jane',
                'last_name'      => 'Doe',
                'changed_fields' => ['email', 'phone'],
            ],
            'contact.opted_in' => [
                'contact_id' => '01JEXAMPLE123456789',
                'phone'      => '+15551234567',
                'source'     => 'keyword',
                'channel'    => 'sms',
            ],
            'contact.opted_out' => [
                'contact_id' => '01JEXAMPLE123456789',
                'phone'      => '+15551234567',
                'source'     => 'keyword',
                'channel'    => 'sms',
            ],
            'contact.bounced' => [
                'contact_id' => '01JEXAMPLE123456789',
                'phone'      => '+15551234567',
                'email'      => 'jane@example.com',
                'error_code' => '30003',
            ],
            'contact.complained' => [
                'contact_id' => '01JEXAMPLE123456789',
                'phone'      => '+15551234567',
                'email'      => 'jane@example.com',
                'error_code' => 'spam_report',
            ],
            'sms.received' => [
                'from'    => '+15551234567',
                'to'      => '+15559876543',
                'body'    => 'Hello, I need help',
                'gateway' => 'twilio',
            ],
            'campaign.started' => [
                'campaign_id'      => '01JEXAMPLE123456789',
                'channel'          => 'sms',
                'total_recipients' => 1500,
            ],
            'campaign.completed' => [
                'campaign_id'   => '01JEXAMPLE123456789',
                'sent_count'    => 1480,
                'failed_count'  => 15,
                'skipped_count' => 5,
            ],
            'campaign.cancelled' => [
                'campaign_id' => '01JEXAMPLE123456789',
            ],
            'flow.started' => [
                'flow_id'      => '01JEXAMPLE123456789',
                'execution_id' => '01JEXAMPLE987654321',
                'trigger_type' => 'contact.opted_in',
            ],
            'flow.completed' => [
                'flow_id'      => '01JEXAMPLE123456789',
                'execution_id' => '01JEXAMPLE987654321',
                'status'       => 'completed',
            ],
            'subscription.confirmed' => [
                'contact_id' => '01JEXAMPLE123456789',
                'form_id'    => '01JEXAMPLE555666777',
            ],
            default => [],
        };
    }

    private static function serializeMessageSent(MessageSentEvent $e): array
    {
        return [
            'message_id'  => $e->messageId,
            'channel'     => $e->channel,
            'recipient'   => $e->recipient,
            'gateway'     => $e->gatewayId,
            'status'      => $e->result->status,
            'provider_id' => $e->result->providerId,
        ];
    }

    private static function serializeMessageFailed(MessageFailedEvent $e): array
    {
        return [
            'channel'   => $e->channel,
            'recipient' => $e->recipient,
            'gateway'   => $e->gatewayId,
            'error'     => $e->error,
        ];
    }

    private static function serializeContactOptedIn(ContactOptedInEvent $e): array
    {
        return [
            'contact_id' => $e->contactIds[0] ?? null,
            'phone'      => $e->phone,
            'source'     => $e->source,
            'channel'    => $e->channel,
        ];
    }

    private static function serializeContactOptedOut(ContactOptedOutEvent $e): array
    {
        return [
            'contact_id' => $e->contactIds[0] ?? null,
            'phone'      => $e->phone,
            'source'     => $e->source,
            'channel'    => $e->channel,
        ];
    }

    /**
     * Bounced/complained events only carry contactId — phone/email enrichment
     * is handled by OutboundWebhookDispatcher before calling this.
     */
    private static function serializeContactIssue(ContactBouncedEvent|ContactComplainedEvent $e): array
    {
        return [
            'contact_id' => $e->contactId,
            'error_code' => $e->errorCode,
        ];
    }

    private static function serializeInboundSms(InboundSmsReceivedEvent $e): array
    {
        return [
            'from'    => $e->from,
            'to'      => $e->to,
            'body'    => $e->body,
            'gateway' => $e->gatewayId,
        ];
    }

    private static function serializeCampaignStarted(CampaignStartedEvent $e): array
    {
        return [
            'campaign_id'      => $e->campaignId,
            'channel'          => $e->channel,
            'total_recipients' => $e->totalRecipients,
        ];
    }

    private static function serializeCampaignCompleted(CampaignCompletedEvent $e): array
    {
        return [
            'campaign_id'   => $e->campaignId,
            'sent_count'    => $e->sentCount,
            'failed_count'  => $e->failedCount,
            'skipped_count' => $e->skippedCount,
        ];
    }

    private static function serializeCampaignCancelled(CampaignCancelledEvent $e): array
    {
        return [
            'campaign_id' => $e->campaignId,
        ];
    }

    private static function serializeFlowStarted(FlowStartedEvent $e): array
    {
        return [
            'flow_id'      => $e->flowId,
            'execution_id' => $e->executionId,
            'trigger_type' => $e->triggerType,
        ];
    }

    private static function serializeFlowCompleted(FlowCompletedEvent $e): array
    {
        return [
            'flow_id'      => $e->flowId,
            'execution_id' => $e->executionId,
            'status'       => $e->status,
        ];
    }

    private static function serializeContactCreated(array $args): array
    {
        [$id, $data] = $args;

        return [
            'contact_id' => $id,
            'email'      => $data['email'] ?? null,
            'phone'      => $data['phone'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'source'     => $data['source'] ?? 'manual',
        ];
    }

    private static function serializeContactUpdated(array $args): array
    {
        [$id, $data] = $args;

        return [
            'contact_id'     => $id,
            'email'          => $data['email'] ?? null,
            'phone'          => $data['phone'] ?? null,
            'first_name'     => $data['first_name'] ?? null,
            'last_name'      => $data['last_name'] ?? null,
            'changed_fields' => array_keys($data),
        ];
    }

    private static function serializeSubscriptionConfirmed(array $args): array
    {
        [$contactId, $formId] = $args;

        return [
            'contact_id' => $contactId,
            'form_id'    => $formId,
        ];
    }
}
