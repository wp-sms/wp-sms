<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WSms\Auth\RateLimiter;
use WSms\Log\Contracts\MessageLoggerInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Rest\GatewayCallbackController;

class GatewayCallbackControllerTest extends TestCase
{
    private GatewayRegistry $registry;
    private MessageLoggerInterface $logger;
    private RateLimiter $rateLimiter;
    private GatewayCallbackController $controller;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_transients'] = [];

        $this->registry = new GatewayRegistry();
        $this->logger = $this->createMock(MessageLoggerInterface::class);
        $this->rateLimiter = new RateLimiter();
        $this->controller = new GatewayCallbackController(
            $this->registry,
            $this->logger,
            $this->rateLimiter,
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_options'],
            $GLOBALS['_test_transients'],
        );
    }

    private function createRequest(string $gatewayId, array $params = [], array $headers = []): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', "/wsms/v1/callbacks/{$gatewayId}/status");
        $request->set_param('gateway_id', $gatewayId);

        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }
        foreach ($headers as $key => $value) {
            $request->set_header($key, $value);
        }

        return $request;
    }

    public function testRejectsUnknownGateway(): void
    {
        $request = $this->createRequest('nonexistent');
        $this->assertFalse($this->controller->checkPermission($request));
    }

    public function testRejectsGatewayWithoutCallbackSupport(): void
    {
        $gateway = $this->createMock(AbstractProvider::class);
        $gateway->method('getId')->willReturn('basic');
        $gateway->method('getSupportedChannels')->willReturn(['sms']);
        $this->registry->register($gateway);

        $request = $this->createRequest('basic');
        $this->assertFalse($this->controller->checkPermission($request));
    }

    public function testRejectsInvalidSignature(): void
    {
        $gateway = $this->createCallbackGateway('test_gw', false);
        $this->registry->register($gateway);

        $request = $this->createRequest('test_gw');
        $this->assertFalse($this->controller->checkPermission($request));
    }

    public function testAcceptsValidSignature(): void
    {
        $gateway = $this->createCallbackGateway('test_gw', true);
        $this->registry->register($gateway);

        $request = $this->createRequest('test_gw');
        $this->assertTrue($this->controller->checkPermission($request));
    }

    public function testUpdatesMessageLogOnValidCallback(): void
    {
        $gateway = $this->createCallbackGateway('test_gw', true, [
            new StatusUpdate('MSG_123', 'delivered'),
        ]);
        $this->registry->register($gateway);

        $this->logger->method('findByProviderId')
            ->with('test_gw', 'MSG_123')
            ->willReturn(['id' => 'log_abc', 'status' => 'sent']);

        $this->logger->expects($this->once())
            ->method('updateStatus')
            ->with('log_abc', 'delivered', null);

        $request = $this->createRequest('test_gw');
        $response = $this->controller->handleStatusCallback($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    public function testHandlesBatchCallbacks(): void
    {
        $gateway = $this->createCallbackGateway('test_gw', true, [
            new StatusUpdate('MSG_1', 'delivered'),
            new StatusUpdate('MSG_2', 'failed', '30006', 'Landline'),
        ]);
        $this->registry->register($gateway);

        $returnMap = [
            ['test_gw', 'MSG_1', ['id' => 'log_1', 'status' => 'sent']],
            ['test_gw', 'MSG_2', ['id' => 'log_2', 'status' => 'sent']],
        ];
        $this->logger->method('findByProviderId')->willReturnMap($returnMap);

        $this->logger->expects($this->exactly(2))->method('updateStatus');

        $request = $this->createRequest('test_gw');
        $response = $this->controller->handleStatusCallback($request);

        $this->assertSame(200, $response->get_status());
    }

    public function testReturns200WhenLogEntryNotFound(): void
    {
        $gateway = $this->createCallbackGateway('test_gw', true, [
            new StatusUpdate('MSG_unknown', 'delivered'),
        ]);
        $this->registry->register($gateway);

        $this->logger->method('findByProviderId')->willReturn(null);
        $this->logger->expects($this->never())->method('updateStatus');

        $request = $this->createRequest('test_gw');
        $response = $this->controller->handleStatusCallback($request);

        $this->assertSame(200, $response->get_status());
    }

    public function testRateLimitsExcessiveRequests(): void
    {
        $gateway = $this->createCallbackGateway('test_gw', true, []);
        $this->registry->register($gateway);

        // Exhaust the rate limit
        for ($i = 0; $i < 120; $i++) {
            $this->rateLimiter->check('gateway_callback_test_gw', 120, 60);
        }

        $request = $this->createRequest('test_gw');
        $response = $this->controller->handleStatusCallback($request);

        $this->assertSame(429, $response->get_status());
        $this->assertArrayHasKey('retry_after', $response->get_data());
    }

    public function testPassesErrorInfoToUpdateStatus(): void
    {
        $gateway = $this->createCallbackGateway('test_gw', true, [
            new StatusUpdate('MSG_err', 'failed', '30006', 'Landline unreachable'),
        ]);
        $this->registry->register($gateway);

        $this->logger->method('findByProviderId')
            ->willReturn(['id' => 'log_err', 'status' => 'sent']);

        $this->logger->expects($this->once())
            ->method('updateStatus')
            ->with('log_err', 'failed', 'Landline unreachable');

        $request = $this->createRequest('test_gw');
        $this->controller->handleStatusCallback($request);
    }

    public function testPassesErrorCodeWhenNoMessage(): void
    {
        $gateway = $this->createCallbackGateway('test_gw', true, [
            new StatusUpdate('MSG_code', 'failed', '30006', null),
        ]);
        $this->registry->register($gateway);

        $this->logger->method('findByProviderId')
            ->willReturn(['id' => 'log_code', 'status' => 'sent']);

        $this->logger->expects($this->once())
            ->method('updateStatus')
            ->with('log_code', 'failed', '30006');

        $request = $this->createRequest('test_gw');
        $this->controller->handleStatusCallback($request);
    }

    /**
     * @return AbstractProvider&SupportsStatusCallback
     */
    private function createCallbackGateway(string $id, bool $validSignature, array $statusUpdates = []): AbstractProvider
    {
        return new class($id, $validSignature, $statusUpdates) extends AbstractProvider implements SupportsStatusCallback {
            public function __construct(
                private readonly string $id,
                private readonly bool $validSignature,
                private readonly array $statusUpdates,
            ) {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getName(): string
            {
                return 'Test Gateway';
            }

            public function getSupportedChannels(): array
            {
                return ['sms'];
            }

            public function getConfigSchema(): array
            {
                return ['shared' => []];
            }

            protected function doSend(\WSms\Messaging\Contracts\MessageInterface $message): \WSms\Messaging\Contracts\DeliveryResult
            {
                return \WSms\Messaging\Contracts\DeliveryResult::sent('test');
            }

            public function validateStatusCallback(\WP_REST_Request $request): bool
            {
                return $this->validSignature;
            }

            public function parseStatusCallback(\WP_REST_Request $request): array
            {
                return $this->statusUpdates;
            }

            public function getStatusCallbackUrl(): string
            {
                return 'http://localhost/wp-json/wsms/v1/callbacks/' . $this->id . '/status';
            }
        };
    }
}
