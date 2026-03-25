<?php

namespace WSms\Tests\Unit\Webhook;

use PHPUnit\Framework\TestCase;
use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\ContactBouncedEvent;
use WSms\Event\Events\ContactComplainedEvent;
use WSms\Event\Events\MessageSentEvent;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Webhook\DeliverWebhookJob;
use WSms\Webhook\OutboundWebhookDispatcher;
use WSms\Webhook\WebhookRepository;
use WSms\Dependencies\Psr\Log\NullLogger;

class OutboundWebhookDispatcherTest extends TestCase
{
    private EventDispatcherInterface $events;
    private WebhookRepository $repo;
    private QueueInterface $queue;
    private ContactRepositoryInterface $contacts;
    private OutboundWebhookDispatcher $dispatcher;

    /** @var DeliverWebhookJob[] */
    private array $dispatchedJobs = [];

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_actions'] = [];

        $this->events = $this->createMock(EventDispatcherInterface::class);
        $this->repo = new WebhookRepository();
        $this->queue = $this->createMock(QueueInterface::class);
        $this->contacts = $this->createMock(ContactRepositoryInterface::class);

        $this->queue->method('dispatch')->willReturnCallback(function (DeliverWebhookJob $job) {
            $this->dispatchedJobs[] = $job;
            return 'job-id';
        });

        $this->dispatcher = new OutboundWebhookDispatcher(
            $this->events,
            $this->repo,
            $this->queue,
            new NullLogger(),
            $this->contacts,
        );
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_options'], $GLOBALS['_test_actions']);
        $this->dispatchedJobs = [];
    }

    public function test_matching_active_webhook_dispatches_job(): void
    {
        $this->repo->save([
            'name'   => 'Test',
            'url'    => 'https://hooks.example.com/abc',
            'events' => ['message.sent'],
            'status' => 'active',
            'secret' => 'secret123',
        ]);

        $event = new MessageSentEvent('msg-1', 'sms', '+1555', 'twilio', DeliveryResult::sent('SM1'));

        $this->dispatcher->handleEvent($event);

        $this->assertCount(1, $this->dispatchedJobs);
        $payload = $this->dispatchedJobs[0]->getPayload();
        $this->assertSame('message.sent', $payload['event']);
        $this->assertSame('https://hooks.example.com/abc', $payload['url']);
        $this->assertSame('secret123', $payload['secret']);
        $this->assertSame('msg-1', $payload['payload']['data']['message_id']);
    }

    public function test_paused_webhook_does_not_dispatch_job(): void
    {
        $this->repo->save([
            'name'   => 'Paused',
            'url'    => 'https://hooks.example.com/abc',
            'events' => ['message.sent'],
            'status' => 'paused',
            'secret' => 'secret123',
        ]);

        $event = new MessageSentEvent('msg-1', 'sms', '+1555', 'twilio', DeliveryResult::sent('SM1'));

        $this->dispatcher->handleEvent($event);

        $this->assertCount(0, $this->dispatchedJobs);
    }

    public function test_no_matching_webhooks_dispatches_nothing(): void
    {
        $this->repo->save([
            'name'   => 'Other',
            'url'    => 'https://hooks.example.com/abc',
            'events' => ['contact.created'],
            'status' => 'active',
            'secret' => 'secret123',
        ]);

        $event = new MessageSentEvent('msg-1', 'sms', '+1555', 'twilio', DeliveryResult::sent('SM1'));

        $this->dispatcher->handleEvent($event);

        $this->assertCount(0, $this->dispatchedJobs);
    }

    public function test_contact_bounced_event_enriched_with_contact_data(): void
    {
        $this->repo->save([
            'name'   => 'Bounce',
            'url'    => 'https://hooks.example.com/abc',
            'events' => ['contact.bounced'],
            'status' => 'active',
            'secret' => 'secret123',
        ]);

        $this->contacts->method('find')
            ->with('c-1')
            ->willReturn(['phone' => '+1555', 'email' => 'jane@example.com']);

        $event = new ContactBouncedEvent('c-1', '30003');

        $this->dispatcher->handleEvent($event);

        $this->assertCount(1, $this->dispatchedJobs);
        $data = $this->dispatchedJobs[0]->getPayload()['payload']['data'];
        $this->assertSame('c-1', $data['contact_id']);
        $this->assertSame('+1555', $data['phone']);
        $this->assertSame('jane@example.com', $data['email']);
    }

    public function test_contact_complained_event_enriched(): void
    {
        $this->repo->save([
            'name'   => 'Complaint',
            'url'    => 'https://hooks.example.com/abc',
            'events' => ['contact.complained'],
            'status' => 'active',
            'secret' => 'secret123',
        ]);

        $this->contacts->method('find')
            ->with('c-1')
            ->willReturn(['phone' => '+1555', 'email' => 'jane@example.com']);

        $event = new ContactComplainedEvent('c-1', 'spam_report');

        $this->dispatcher->handleEvent($event);

        $this->assertCount(1, $this->dispatchedJobs);
        $data = $this->dispatchedJobs[0]->getPayload()['payload']['data'];
        $this->assertSame('jane@example.com', $data['email']);
    }

    public function test_contact_enrichment_graceful_on_missing_contact(): void
    {
        $this->repo->save([
            'name'   => 'Bounce',
            'url'    => 'https://hooks.example.com/abc',
            'events' => ['contact.bounced'],
            'status' => 'active',
            'secret' => 'secret123',
        ]);

        $this->contacts->method('find')->with('c-deleted')->willReturn(null);

        $event = new ContactBouncedEvent('c-deleted', '30003');

        $this->dispatcher->handleEvent($event);

        $this->assertCount(1, $this->dispatchedJobs);
        $data = $this->dispatchedJobs[0]->getPayload()['payload']['data'];
        $this->assertSame('c-deleted', $data['contact_id']);
        $this->assertArrayNotHasKey('phone', $data);
    }

    public function test_hook_event_dispatches_correctly(): void
    {
        $this->repo->save([
            'name'   => 'ContactCreate',
            'url'    => 'https://hooks.example.com/abc',
            'events' => ['contact.created'],
            'status' => 'active',
            'secret' => 'secret123',
        ]);

        $this->dispatcher->handleHookEvent('contact.created', [
            'c-1',
            ['email' => 'jane@example.com', 'phone' => '+1555', 'first_name' => 'Jane', 'last_name' => 'Doe', 'source' => 'import'],
        ]);

        $this->assertCount(1, $this->dispatchedJobs);
        $payload = $this->dispatchedJobs[0]->getPayload();
        $this->assertSame('contact.created', $payload['event']);
        $this->assertSame('c-1', $payload['payload']['data']['contact_id']);
        $this->assertSame('jane@example.com', $payload['payload']['data']['email']);
    }

    public function test_multiple_matching_webhooks_dispatch_multiple_jobs(): void
    {
        $this->repo->save([
            'name'   => 'A',
            'url'    => 'https://a.com',
            'events' => ['message.sent'],
            'status' => 'active',
            'secret' => 'a',
        ]);
        $this->repo->save([
            'name'   => 'B',
            'url'    => 'https://b.com',
            'events' => ['message.sent'],
            'status' => 'active',
            'secret' => 'b',
        ]);

        $event = new MessageSentEvent('msg-1', 'sms', '+1555', 'twilio', DeliveryResult::sent('SM1'));

        $this->dispatcher->handleEvent($event);

        $this->assertCount(2, $this->dispatchedJobs);
    }

    public function test_payload_envelope_structure(): void
    {
        $this->repo->save([
            'id'     => 'wh-1',
            'name'   => 'Test',
            'url'    => 'https://hooks.example.com/abc',
            'events' => ['message.sent'],
            'status' => 'active',
            'secret' => 'secret123',
        ]);

        $event = new MessageSentEvent('msg-1', 'sms', '+1555', 'twilio', DeliveryResult::sent('SM1'));

        $this->dispatcher->handleEvent($event);

        $envelope = $this->dispatchedJobs[0]->getPayload()['payload'];
        $this->assertSame('message.sent', $envelope['event']);
        $this->assertSame('wh-1', $envelope['webhook_id']);
        $this->assertArrayHasKey('timestamp', $envelope);
        $this->assertArrayHasKey('data', $envelope);
    }
}
