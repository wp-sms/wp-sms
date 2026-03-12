<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\PolicyEngine;
use WSms\Auth\RateLimiter;
use WSms\Auth\ValueObjects\IdentifyResult;
use WSms\Rest\AuthController;

class AuthControllerIdentifyTest extends TestCase
{
    private AuthController $controller;
    private MockObject&AuthOrchestrator $orchestrator;
    private MockObject&RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->orchestrator = $this->createMock(AuthOrchestrator::class);
        $this->rateLimiter = $this->createMock(RateLimiter::class);

        $this->controller = new AuthController(
            $this->orchestrator,
            $this->rateLimiter,
            $this->createMock(PolicyEngine::class),
        );
    }

    public function testHandleIdentifyReturns200(): void
    {
        $this->allowRateLimit();

        $identifyResult = new IdentifyResult(
            identifierType: 'email',
            userFound: true,
            availableMethods: [['method' => 'password', 'type' => 'password', 'channel' => 'password']],
            defaultMethod: 'password',
            registrationAvailable: false,
            registrationFields: [],
            meta: ['masked_identifier' => 'u***@example.com'],
        );

        $this->orchestrator->method('identify')->willReturn($identifyResult);

        $request = new WP_REST_Request('POST', '/wsms/v1/auth/identify');
        $request->set_param('identifier', 'user@example.com');

        $response = $this->controller->handleIdentify($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame($identifyResult->toArray(), $response->get_data());
    }

    public function testHandleIdentifyRateLimited(): void
    {
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => false, 'remaining' => 0, 'retry_after' => 45,
        ]);

        $request = new WP_REST_Request('POST', '/wsms/v1/auth/identify');
        $request->set_param('identifier', 'user@example.com');

        $response = $this->controller->handleIdentify($request);

        $this->assertSame(429, $response->get_status());
    }

    public function testHandleIdentifyPassesIdentifier(): void
    {
        $this->allowRateLimit();

        $identifyResult = new IdentifyResult(
            identifierType: 'phone',
            userFound: false,
            availableMethods: [],
            defaultMethod: null,
            registrationAvailable: false,
            registrationFields: [],
            meta: [],
        );

        $this->orchestrator->expects($this->once())
            ->method('identify')
            ->with('+1234567890')
            ->willReturn($identifyResult);

        $request = new WP_REST_Request('POST', '/wsms/v1/auth/identify');
        $request->set_param('identifier', '+1234567890');

        $this->controller->handleIdentify($request);
    }

    public function testHandleIdentifyUserNotFoundStill200(): void
    {
        $this->allowRateLimit();

        $identifyResult = new IdentifyResult(
            identifierType: 'email',
            userFound: false,
            availableMethods: [],
            defaultMethod: null,
            registrationAvailable: false,
            registrationFields: [],
            meta: [],
        );

        $this->orchestrator->method('identify')->willReturn($identifyResult);

        $request = new WP_REST_Request('POST', '/wsms/v1/auth/identify');
        $request->set_param('identifier', 'nobody@example.com');

        $response = $this->controller->handleIdentify($request);

        $this->assertSame(200, $response->get_status());
        $this->assertFalse($response->get_data()['user_found']);
    }

    private function allowRateLimit(): void
    {
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => true, 'remaining' => 9, 'retry_after' => 0,
        ]);
    }
}
