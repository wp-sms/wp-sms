<?php

namespace WSms\Tests\Unit\Container;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Container\FlowServiceProvider;
use WSms\Container\ServiceContainer;
use WSms\Flow\Engine\FlowRunner;
use WSms\Log\Contracts\MessageLoggerInterface;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Queue\JobProcessor;

class SendMessageHandlerTest extends TestCase
{
    private ServiceContainer $container;
    private MockObject&GatewayRegistry $registry;
    private MockObject&MessageLoggerInterface $logger;
    private JobProcessor $processor;

    protected function setUp(): void
    {
        $this->container = ServiceContainer::getInstance();
        $this->registry = $this->createMock(GatewayRegistry::class);
        $this->logger = $this->createMock(MessageLoggerInterface::class);
        $this->processor = new JobProcessor($this->createMock(\WSms\Dependencies\Psr\Log\LoggerInterface::class));

        $this->container->singleton('gateway.registry', $this->registry);
        $this->container->singleton('log.message', $this->logger);
        $this->container->singleton('queue.processor', $this->processor);

        // Stub flow.runner to avoid booting flow infrastructure
        $flowRunner = $this->createMock(FlowRunner::class);
        $this->container->singleton('flow.runner', $flowRunner);

        // Capture add_action calls
        $GLOBALS['_test_actions'] = [];

        $provider = new FlowServiceProvider();
        $provider->boot($this->container);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions']);
    }

    private function makePayload(array $overrides = []): array
    {
        return array_merge([
            'type'    => 'send_message',
            'payload' => [
                'gateway_id'   => 'webhook',
                'channel'      => 'webhook',
                'recipient'    => 'https://example.com/hook',
                'body'         => '{"test":true}',
                'log_id'       => 'log-123',
                'execution_id' => 'exec-1',
                'meta'         => [],
            ],
        ], $overrides);
    }

    public function testSuccessUpdatesLogToSent(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->method('send')->willReturn(DeliveryResult::sent('pid-1'));
        $this->registry->method('get')->with('webhook')->willReturn($gateway);

        $this->logger->expects($this->once())
            ->method('updateStatus')
            ->with('log-123', 'sent', null, 'pid-1');

        $this->processor->process($this->makePayload());
    }

    public function testPermanentFailureUpdatesLogToFailed(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->method('send')->willReturn(DeliveryResult::failed('HTTP 404: Not Found', ['http_status' => 404], false));
        $this->registry->method('get')->with('webhook')->willReturn($gateway);

        $this->logger->expects($this->once())
            ->method('updateStatus')
            ->with('log-123', 'failed', 'HTTP 404: Not Found', null);

        $this->processor->process($this->makePayload());
    }

    public function testRetryableFailureThrowsRuntimeException(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->method('send')->willReturn(DeliveryResult::failed('HTTP 503: Service Unavailable', ['http_status' => 503], true));
        $this->registry->method('get')->with('webhook')->willReturn($gateway);

        // The handler should throw, but JobProcessor catches it and calls handleFailure.
        // With retries > 0 it reschedules; with 0 it fires wsms_job_failed.
        // We verify the logger is NOT called (log stays as 'queued').
        $this->logger->expects($this->never())->method('updateStatus');

        // Process with retries so it reschedules (no wsms_job_failed fired)
        $this->processor->process(array_merge($this->makePayload(), [
            'retries' => 2,
            'backoff' => 60,
        ]));
    }

    public function testGatewayNotFoundUpdatesLogToFailed(): void
    {
        $this->registry->method('get')->willReturn(null);

        $this->logger->expects($this->once())
            ->method('updateStatus')
            ->with('log-123', 'failed', 'Gateway not found');

        $this->processor->process($this->makePayload());
    }

    public function testJobFailedListenerUpdatesLogForSendMessage(): void
    {
        $callbacks = $GLOBALS['_test_actions']['wsms_job_failed'] ?? [];
        $this->assertNotEmpty($callbacks, 'wsms_job_failed listener should be registered');

        $listener = $callbacks[0];
        $exception = new \RuntimeException('Transient delivery failure: HTTP 503');

        $this->logger->expects($this->once())
            ->method('updateStatus')
            ->with('log-123', 'failed', 'Transient delivery failure: HTTP 503');

        $listener(
            ['type' => 'send_message', 'payload' => ['log_id' => 'log-123']],
            $exception,
        );
    }

    public function testJobFailedListenerIgnoresNonSendMessageJobs(): void
    {
        $callbacks = $GLOBALS['_test_actions']['wsms_job_failed'] ?? [];
        $listener = $callbacks[0];

        $this->logger->expects($this->never())->method('updateStatus');

        $listener(
            ['type' => 'execute_flow_step', 'payload' => ['log_id' => 'log-456']],
            new \RuntimeException('Some error'),
        );
    }

    public function testJobFailedListenerHandlesMissingLogId(): void
    {
        $callbacks = $GLOBALS['_test_actions']['wsms_job_failed'] ?? [];
        $listener = $callbacks[0];

        $this->logger->expects($this->never())->method('updateStatus');

        $listener(
            ['type' => 'send_message', 'payload' => []],
            new \RuntimeException('Some error'),
        );
    }
}
