<?php

namespace WSms\Tests\Unit\Webhook;

use PHPUnit\Framework\TestCase;
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
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Webhook\WebhookEventMap;

class WebhookEventMapTest extends TestCase
{
    public function test_each_typed_event_class_maps_to_dot_notation_name(): void
    {
        $expected = [
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

        foreach ($expected as $class => $name) {
            $this->assertSame($name, WebhookEventMap::getEventName($class), "Failed for {$class}");
        }
    }

    public function test_unknown_event_class_returns_null(): void
    {
        $this->assertNull(WebhookEventMap::getEventName(\stdClass::class));
    }

    public function test_get_all_event_names_includes_typed_and_hook_events(): void
    {
        $all = WebhookEventMap::getAllEventNames();

        // Typed events
        $this->assertContains('message.sent', $all);
        $this->assertContains('flow.completed', $all);

        // Hook-based events
        $this->assertContains('contact.created', $all);
        $this->assertContains('contact.updated', $all);
        $this->assertContains('subscription.confirmed', $all);
    }

    public function test_get_grouped_events_returns_correct_structure(): void
    {
        $groups = WebhookEventMap::getGroupedEvents();

        $this->assertArrayHasKey('Messages', $groups);
        $this->assertArrayHasKey('Contacts', $groups);
        $this->assertArrayHasKey('Inbound', $groups);
        $this->assertArrayHasKey('Campaigns', $groups);
        $this->assertArrayHasKey('Flows', $groups);
        $this->assertArrayHasKey('Subscribers', $groups);

        // Each entry has required keys
        $first = $groups['Messages'][0];
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('label', $first);
        $this->assertArrayHasKey('description', $first);
        $this->assertArrayHasKey('sample_payload', $first);
    }

    public function test_serialize_message_sent_flattens_delivery_result(): void
    {
        $result = DeliveryResult::sent('SM1234', 1.5);
        $event = new MessageSentEvent('msg-1', 'sms', '+1555', 'twilio', $result);

        $payload = WebhookEventMap::serializePayload('message.sent', $event);

        $this->assertSame('msg-1', $payload['message_id']);
        $this->assertSame('sms', $payload['channel']);
        $this->assertSame('+1555', $payload['recipient']);
        $this->assertSame('twilio', $payload['gateway']);
        $this->assertSame('sent', $payload['status']);
        $this->assertSame('SM1234', $payload['provider_id']);
    }

    public function test_serialize_message_failed(): void
    {
        $event = new MessageFailedEvent('sms', '+1555', 'twilio', 'Invalid number');

        $payload = WebhookEventMap::serializePayload('message.failed', $event);

        $this->assertSame('sms', $payload['channel']);
        $this->assertSame('+1555', $payload['recipient']);
        $this->assertSame('twilio', $payload['gateway']);
        $this->assertSame('Invalid number', $payload['error']);
    }

    public function test_serialize_contact_opted_in(): void
    {
        $event = new ContactOptedInEvent(['c-1', 'c-2'], '+1555', 'keyword', null, null, 'sms');

        $payload = WebhookEventMap::serializePayload('contact.opted_in', $event);

        $this->assertSame('c-1', $payload['contact_id']);
        $this->assertSame('+1555', $payload['phone']);
        $this->assertSame('keyword', $payload['source']);
        $this->assertSame('sms', $payload['channel']);
    }

    public function test_serialize_contact_opted_out(): void
    {
        $event = new ContactOptedOutEvent(['c-1'], '+1555', 'manual', null, null, 'sms');

        $payload = WebhookEventMap::serializePayload('contact.opted_out', $event);

        $this->assertSame('c-1', $payload['contact_id']);
        $this->assertSame('+1555', $payload['phone']);
    }

    public function test_serialize_contact_bounced_minimal(): void
    {
        $event = new ContactBouncedEvent('c-1', '30003');

        $payload = WebhookEventMap::serializePayload('contact.bounced', $event);

        $this->assertSame('c-1', $payload['contact_id']);
        $this->assertSame('30003', $payload['error_code']);
        // No phone/email — enrichment is handled by the dispatcher
        $this->assertArrayNotHasKey('phone', $payload);
    }

    public function test_serialize_contact_complained_minimal(): void
    {
        $event = new ContactComplainedEvent('c-1', 'spam_report');

        $payload = WebhookEventMap::serializePayload('contact.complained', $event);

        $this->assertSame('c-1', $payload['contact_id']);
        $this->assertSame('spam_report', $payload['error_code']);
    }

    public function test_serialize_inbound_sms(): void
    {
        $event = new InboundSmsReceivedEvent('+1555', '+1666', 'Hello', 'twilio');

        $payload = WebhookEventMap::serializePayload('sms.received', $event);

        $this->assertSame('+1555', $payload['from']);
        $this->assertSame('+1666', $payload['to']);
        $this->assertSame('Hello', $payload['body']);
        $this->assertSame('twilio', $payload['gateway']);
    }

    public function test_serialize_campaign_started(): void
    {
        $event = new CampaignStartedEvent('camp-1', 'sms', 1500);

        $payload = WebhookEventMap::serializePayload('campaign.started', $event);

        $this->assertSame('camp-1', $payload['campaign_id']);
        $this->assertSame('sms', $payload['channel']);
        $this->assertSame(1500, $payload['total_recipients']);
    }

    public function test_serialize_campaign_completed(): void
    {
        $event = new CampaignCompletedEvent('camp-1', 1480, 15, 5);

        $payload = WebhookEventMap::serializePayload('campaign.completed', $event);

        $this->assertSame('camp-1', $payload['campaign_id']);
        $this->assertSame(1480, $payload['sent_count']);
        $this->assertSame(15, $payload['failed_count']);
        $this->assertSame(5, $payload['skipped_count']);
    }

    public function test_serialize_campaign_cancelled(): void
    {
        $event = new CampaignCancelledEvent('camp-1');

        $payload = WebhookEventMap::serializePayload('campaign.cancelled', $event);

        $this->assertSame('camp-1', $payload['campaign_id']);
    }

    public function test_serialize_flow_started(): void
    {
        $event = new FlowStartedEvent('flow-1', 'exec-1', 'contact.opted_in', []);

        $payload = WebhookEventMap::serializePayload('flow.started', $event);

        $this->assertSame('flow-1', $payload['flow_id']);
        $this->assertSame('exec-1', $payload['execution_id']);
        $this->assertSame('contact.opted_in', $payload['trigger_type']);
    }

    public function test_serialize_flow_completed(): void
    {
        $event = new FlowCompletedEvent('flow-1', 'exec-1', 'completed');

        $payload = WebhookEventMap::serializePayload('flow.completed', $event);

        $this->assertSame('flow-1', $payload['flow_id']);
        $this->assertSame('exec-1', $payload['execution_id']);
        $this->assertSame('completed', $payload['status']);
    }

    public function test_serialize_hook_contact_created(): void
    {
        $payload = WebhookEventMap::serializeHookPayload('contact.created', [
            'c-1',
            ['email' => 'jane@example.com', 'phone' => '+1555', 'first_name' => 'Jane', 'last_name' => 'Doe', 'source' => 'import'],
        ]);

        $this->assertSame('c-1', $payload['contact_id']);
        $this->assertSame('jane@example.com', $payload['email']);
        $this->assertSame('+1555', $payload['phone']);
        $this->assertSame('Jane', $payload['first_name']);
        $this->assertSame('Doe', $payload['last_name']);
        $this->assertSame('import', $payload['source']);
    }

    public function test_serialize_hook_contact_updated(): void
    {
        $payload = WebhookEventMap::serializeHookPayload('contact.updated', [
            'c-1',
            ['email' => 'new@example.com', 'phone' => '+1555'],
        ]);

        $this->assertSame('c-1', $payload['contact_id']);
        $this->assertSame('new@example.com', $payload['email']);
        $this->assertContains('email', $payload['changed_fields']);
        $this->assertContains('phone', $payload['changed_fields']);
    }

    public function test_serialize_hook_subscription_confirmed(): void
    {
        $payload = WebhookEventMap::serializeHookPayload('subscription.confirmed', ['c-1', 'form-1']);

        $this->assertSame('c-1', $payload['contact_id']);
        $this->assertSame('form-1', $payload['form_id']);
    }

    public function test_sample_payloads_match_serialized_field_names(): void
    {
        // For typed events, sample payload field names should match serialized payload field names.
        $result = DeliveryResult::sent('SM1234');
        $testEvents = [
            'message.sent'       => new MessageSentEvent('msg-1', 'sms', '+1555', 'twilio', $result),
            'message.failed'     => new MessageFailedEvent('sms', '+1555', 'twilio', 'err'),
            'campaign.started'   => new CampaignStartedEvent('c-1', 'sms', 100),
            'campaign.completed' => new CampaignCompletedEvent('c-1', 90, 5, 5),
            'campaign.cancelled' => new CampaignCancelledEvent('c-1'),
            'flow.started'       => new FlowStartedEvent('f-1', 'e-1', 'trigger', []),
            'flow.completed'     => new FlowCompletedEvent('f-1', 'e-1', 'done'),
            'sms.received'       => new InboundSmsReceivedEvent('+1555', '+1666', 'hi', 'twilio'),
        ];

        foreach ($testEvents as $name => $event) {
            $serialized = WebhookEventMap::serializePayload($name, $event);
            $sample = WebhookEventMap::getSamplePayload($name);

            $this->assertSame(
                array_keys($sample),
                array_keys($serialized),
                "Sample/serialized field mismatch for {$name}"
            );
        }
    }

    public function test_unknown_event_name_returns_empty_payload(): void
    {
        $this->assertSame([], WebhookEventMap::serializePayload('unknown.event', new \stdClass()));
        $this->assertSame([], WebhookEventMap::serializeHookPayload('unknown.hook', []));
        $this->assertSame([], WebhookEventMap::getSamplePayload('unknown.event'));
    }
}
