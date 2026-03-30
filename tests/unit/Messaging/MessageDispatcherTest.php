<?php

namespace WSms\Tests\Unit\Messaging;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\MessageFailedEvent;
use WSms\Event\Events\MessageSentEvent;
use WSms\Log\Contracts\MessageLoggerInterface;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\Message\Message;
use WSms\Messaging\MessageDispatcher;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\SendMessageJob;

class MessageDispatcherTest extends TestCase
{
    private MockObject&GatewayRegistry $gatewayRegistry;
    private MockObject&MessageLoggerInterface $messageLogger;
    private MockObject&EventDispatcherInterface $eventDispatcher;
    private MockObject&QueueInterface $queue;
    private MessageDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->gatewayRegistry = $this->createMock(GatewayRegistry::class);
        $this->messageLogger = $this->createMock(MessageLoggerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->queue = $this->createMock(QueueInterface::class);

        $this->dispatcher = new MessageDispatcher(
            $this->gatewayRegistry,
            $this->messageLogger,
            $this->eventDispatcher,
            $this->queue,
        );
    }

    public function testSendImmediateSuccess(): void
    {
        $message = new Message('sms','+12025551234', 'Hello');
        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->method('getId')->willReturn('twilio');
        $gateway->method('send')->willReturn(DeliveryResult::sent('provider-123'));

        $this->gatewayRegistry->method('getDefault')->with('sms')->willReturn($gateway);

        $this->messageLogger->expects($this->once())
            ->method('logSend')
            ->with(
                'twilio',
                'sms',
                '+12025551234',
                'Hello',
                'sent',
                null,
                null,
                'provider-123',
                null,
                null,
            )
            ->willReturn('log-1');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof MessageSentEvent
                    && $event->messageId === 'log-1'
                    && $event->channel === 'sms'
                    && $event->recipient === '+12025551234'
                    && $event->gatewayId === 'twilio';
            }));

        $result = $this->dispatcher->sendImmediate($message);

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
    }

    public function testSendImmediateFailure(): void
    {
        $message = new Message('sms','+12025551234', 'Hello');
        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->method('getId')->willReturn('twilio');
        $gateway->method('send')->willReturn(DeliveryResult::failed('Network error'));

        $this->gatewayRegistry->method('getDefault')->with('sms')->willReturn($gateway);

        $this->messageLogger->expects($this->once())
            ->method('logSend')
            ->with(
                'twilio',
                'sms',
                '+12025551234',
                'Hello',
                'failed',
                $this->anything(),
                $this->anything(),
                $this->anything(),
                'Network error',
                $this->anything(),
            )
            ->willReturn('log-2');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof MessageFailedEvent
                    && $event->channel === 'sms'
                    && $event->recipient === '+12025551234'
                    && $event->gatewayId === 'twilio'
                    && $event->error === 'Network error';
            }));

        $result = $this->dispatcher->sendImmediate($message);

        $this->assertFalse($result->success);
        $this->assertSame('Network error', $result->error);
    }

    public function testSendImmediateReturnsFailedWhenNoGateway(): void
    {
        $message = new Message('sms','+12025551234', 'Hello');

        $this->gatewayRegistry->method('getDefault')->with('sms')->willReturn(null);

        $this->messageLogger->expects($this->never())->method('logSend');
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $result = $this->dispatcher->sendImmediate($message);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('sms', $result->error);
    }

    public function testSendQueuedLogsAndDispatchesJob(): void
    {
        $message = new Message('sms','+12025551234', 'Queued msg', 'exec-1');
        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->method('getId')->willReturn('twilio');

        $this->gatewayRegistry->method('getDefault')->with('sms')->willReturn($gateway);

        $this->messageLogger->expects($this->once())
            ->method('logSend')
            ->with(
                'twilio',
                'sms',
                '+12025551234',
                'Queued msg',
                'queued',
                'exec-1',
                $this->anything(),
            )
            ->willReturn('log-3');

        $this->queue->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($job) {
                return $job instanceof SendMessageJob
                    && $job->getPayload()['channel'] === 'sms'
                    && $job->getPayload()['recipient'] === '+12025551234';
            }))
            ->willReturn('job-1');

        $logId = $this->dispatcher->sendQueued($message);

        $this->assertSame('log-3', $logId);
    }

    public function testSendQueuedWithSpecificGatewayUsesIt(): void
    {
        $message = new Message('sms', '+12025551234', 'Queued msg', 'exec-2');
        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->method('getId')->willReturn('vonage');

        $this->gatewayRegistry->expects($this->once())
            ->method('get')
            ->with('vonage')
            ->willReturn($gateway);

        // getDefault should NOT be called when explicit gateway is provided
        $this->gatewayRegistry->expects($this->never())
            ->method('getDefault');

        $this->messageLogger->expects($this->once())
            ->method('logSend')
            ->with(
                'vonage',
                'sms',
                '+12025551234',
                'Queued msg',
                'queued',
                'exec-2',
                $this->anything(),
            )
            ->willReturn('log-4');

        $this->queue->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($job) {
                return $job instanceof SendMessageJob
                    && $job->getPayload()['gateway_id'] === 'vonage';
            }))
            ->willReturn('job-2');

        $logId = $this->dispatcher->sendQueued($message, 'vonage');

        $this->assertSame('log-4', $logId);
    }

    public function testSendQueuedWithNullGatewayUsesChannelDefault(): void
    {
        $message = new Message('sms', '+12025551234', 'Queued msg');
        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->method('getId')->willReturn('twilio');

        $this->gatewayRegistry->expects($this->once())
            ->method('getDefault')
            ->with('sms')
            ->willReturn($gateway);

        $this->gatewayRegistry->expects($this->never())
            ->method('get');

        $this->messageLogger->method('logSend')->willReturn('log-5');
        $this->queue->method('dispatch')->willReturn('job-3');

        $logId = $this->dispatcher->sendQueued($message, null);

        $this->assertSame('log-5', $logId);
    }

    // --- canDeliverToChannel ---

    public function testCanDeliverToChannelReturnsTrueWhenGatewayExists(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $this->gatewayRegistry->method('getDefault')->with('sms')->willReturn($gateway);

        $this->assertTrue($this->dispatcher->canDeliverToChannel('sms'));
    }

    public function testCanDeliverToChannelReturnsFalseWhenNoGateway(): void
    {
        $this->gatewayRegistry->method('getDefault')->with('sms')->willReturn(null);

        $this->assertFalse($this->dispatcher->canDeliverToChannel('sms'));
    }

    public function testCanDeliverToChannelUsesSpecificGatewayId(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $this->gatewayRegistry->method('get')->with('twilio-otp')->willReturn($gateway);

        $this->assertTrue($this->dispatcher->canDeliverToChannel('sms', 'twilio-otp'));
    }

    public function testCanDeliverToChannelReturnsFalseForMissingSpecificGateway(): void
    {
        $this->gatewayRegistry->method('get')->with('nonexistent')->willReturn(null);

        $this->assertFalse($this->dispatcher->canDeliverToChannel('sms', 'nonexistent'));
    }
}
