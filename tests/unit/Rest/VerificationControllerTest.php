<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Auth\RateLimiter;
use WSms\Rest\VerificationController;
use WSms\Verification\VerificationResult;
use WSms\Verification\VerificationService;

class VerificationControllerTest extends TestCase
{
    private VerificationController $controller;
    private MockObject&VerificationService $service;
    private MockObject&RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $GLOBALS['_test_current_user_id'] = 0;
        $this->service = $this->createMock(VerificationService::class);
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->controller = new VerificationController($this->service, $this->rateLimiter);
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_current_user_id'] = 0;
    }

    public function testRegisterRoutesDoesNotThrow(): void
    {
        $this->controller->registerRoutes();
        $this->assertTrue(true); // No exception means routes registered.
    }

    public function testHandleSendReturns429WhenRateLimited(): void
    {
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => false,
            'remaining' => 0,
            'retry_after' => 30,
        ]);

        $request = new \WP_REST_Request();
        $request->set_param('channel', 'email');
        $request->set_param('identifier', 'test@example.com');

        $response = $this->controller->handleSend($request);

        $this->assertSame(429, $response->get_status());
        $this->assertSame('rate_limited', $response->get_data()['error']);
    }

    public function testHandleSendReturnsSuccessResponse(): void
    {
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => true,
            'remaining' => 4,
            'retry_after' => 0,
        ]);

        $this->service->method('sendCode')->willReturn(
            VerificationResult::codeSent('token123', 't**t@example.com', 300),
        );

        $request = new \WP_REST_Request();
        $request->set_param('channel', 'email');
        $request->set_param('identifier', 'test@example.com');

        $response = $this->controller->handleSend($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertSame('token123', $response->get_data()['session_token']);
    }

    public function testHandleSendPassesSessionTokenFromHeader(): void
    {
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => true,
            'remaining' => 4,
            'retry_after' => 0,
        ]);

        $this->service->expects($this->once())
            ->method('sendCode')
            ->with('email', 'test@example.com', 'existing-token', null)
            ->willReturn(VerificationResult::codeSent('existing-token', 't**t@example.com', 300));

        $request = new \WP_REST_Request();
        $request->set_param('channel', 'email');
        $request->set_param('identifier', 'test@example.com');
        $request->set_header('X-Verification-Session', 'existing-token');

        $this->controller->handleSend($request);
    }

    public function testHandleCheckReturns400WhenNoSessionToken(): void
    {
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => true,
            'remaining' => 9,
            'retry_after' => 0,
        ]);

        $request = new \WP_REST_Request();
        $request->set_param('channel', 'email');
        $request->set_param('identifier', 'test@example.com');
        $request->set_param('code', '123456');

        $response = $this->controller->handleCheck($request);

        $this->assertSame(400, $response->get_status());
        $this->assertSame('missing_session', $response->get_data()['error']);
    }

    public function testHandleCheckReturnsSuccessOnVerification(): void
    {
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => true,
            'remaining' => 9,
            'retry_after' => 0,
        ]);

        $this->service->method('verifyCode')->willReturn(
            VerificationResult::verified('token123'),
        );

        $request = new \WP_REST_Request();
        $request->set_param('channel', 'email');
        $request->set_param('identifier', 'test@example.com');
        $request->set_param('code', '123456');
        $request->set_header('X-Verification-Session', 'token123');

        $response = $this->controller->handleCheck($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    public function testHandleCheckReturns400OnFailedVerification(): void
    {
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => true,
            'remaining' => 9,
            'retry_after' => 0,
        ]);

        $this->service->method('verifyCode')->willReturn(
            VerificationResult::failed('invalid_code', 'Invalid code.'),
        );

        $request = new \WP_REST_Request();
        $request->set_param('channel', 'email');
        $request->set_param('identifier', 'test@example.com');
        $request->set_param('code', '000000');
        $request->set_header('X-Verification-Session', 'token123');

        $response = $this->controller->handleCheck($request);

        $this->assertSame(400, $response->get_status());
        $this->assertFalse($response->get_data()['success']);
    }

    public function testHandleStatusReturns400WhenNoSessionToken(): void
    {
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => true,
            'remaining' => 19,
            'retry_after' => 0,
        ]);

        $request = new \WP_REST_Request();
        $request->set_param('channel', 'email');
        $request->set_param('identifier', 'test@example.com');

        $response = $this->controller->handleStatus($request);

        $this->assertSame(400, $response->get_status());
    }

    public function testHandleStatusReturnsVerifiedTrue(): void
    {
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => true,
            'remaining' => 19,
            'retry_after' => 0,
        ]);

        $this->service->method('isVerified')->willReturn(true);

        $request = new \WP_REST_Request();
        $request->set_param('channel', 'email');
        $request->set_param('identifier', 'test@example.com');
        $request->set_header('X-Verification-Session', 'token123');

        $response = $this->controller->handleStatus($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['verified']);
    }

    public function testHandleStatusReturnsVerifiedFalse(): void
    {
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => true,
            'remaining' => 19,
            'retry_after' => 0,
        ]);

        $this->service->method('isVerified')->willReturn(false);

        $request = new \WP_REST_Request();
        $request->set_param('channel', 'email');
        $request->set_param('identifier', 'test@example.com');
        $request->set_header('X-Verification-Session', 'token123');

        $response = $this->controller->handleStatus($request);

        $this->assertSame(200, $response->get_status());
        $this->assertFalse($response->get_data()['verified']);
    }
}
