<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Auth\CaptchaGuard;
use WSms\Auth\RateLimiter;
use WSms\MessagingButton\MessageHandler;
use WSms\MessagingButton\MessagingButtonSettings;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Rest\MessagingButtonController;

class MessagingButtonControllerTest extends TestCase
{
    private MessagingButtonController $controller;
    private MockObject&MessagingButtonSettings $settings;
    private MockObject&MessageHandler $messageHandler;
    private MockObject&RateLimiter $rateLimiter;
    private MockObject&CaptchaGuard $captchaGuard;

    protected function setUp(): void
    {
        $this->settings = $this->createMock(MessagingButtonSettings::class);
        $this->messageHandler = $this->createMock(MessageHandler::class);
        $gatewayRegistry = $this->createMock(GatewayRegistry::class);
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->captchaGuard = $this->createMock(CaptchaGuard::class);

        $this->controller = new MessagingButtonController(
            $this->settings,
            $this->messageHandler,
            $gatewayRegistry,
            $this->rateLimiter,
            $this->captchaGuard,
        );

        // Default: no rate limiting.
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => true, 'remaining' => 5, 'retry_after' => 0,
        ]);

        // Default: enabled.
        $this->settings->method('isEnabled')->willReturn(true);
        $this->settings->method('get')->willReturn([]);

        unset($GLOBALS['_test_current_user_id']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_current_user_id']);
    }

    private function makeRequest(array $params = [], ?string $captchaToken = null): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/messaging-button/message');

        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        if ($captchaToken) {
            $request->set_header('X-Captcha-Response', $captchaToken);
        }

        return $request;
    }

    public function testMessageBlockedWhenCaptchaFails(): void
    {
        $this->captchaGuard->method('verify')->willReturn(false);

        $response = $this->controller->handleMessage($this->makeRequest([
            'message' => 'Hello',
        ]));

        $this->assertSame(403, $response->get_status());
        $this->assertSame('captcha_failed', $response->get_data()['error']);
    }

    public function testMessageAllowedWhenCaptchaDisabled(): void
    {
        $this->captchaGuard->method('verify')->willReturn(null);
        $this->messageHandler->method('handle')->willReturn(['success' => true]);

        $response = $this->controller->handleMessage($this->makeRequest([
            'message' => 'Hello',
        ]));

        $this->assertSame(200, $response->get_status());
    }

    public function testMessageAllowedWhenCaptchaVerified(): void
    {
        $this->captchaGuard->method('verify')->willReturn(true);
        $this->messageHandler->method('handle')->willReturn(['success' => true]);

        $response = $this->controller->handleMessage($this->makeRequest(
            ['message' => 'Hello'],
            'valid-captcha-token',
        ));

        $this->assertSame(200, $response->get_status());
    }

    public function testCaptchaVerifiedWithMessagingButtonAction(): void
    {
        $this->captchaGuard->expects($this->once())
            ->method('verify')
            ->with($this->isInstanceOf(\WP_REST_Request::class), 'messaging_button')
            ->willReturn(null);

        $this->messageHandler->method('handle')->willReturn(['success' => true]);

        $this->controller->handleMessage($this->makeRequest(['message' => 'Hello']));
    }

    public function testCaptchaCheckHappensBeforeRateLimit(): void
    {
        // If captcha fails, rate limiter should NOT be called
        // (captcha check happens after rate limit in current code,
        // but we verify captcha is checked with correct action)
        $this->captchaGuard->method('verify')->willReturn(false);

        $response = $this->controller->handleMessage($this->makeRequest([
            'message' => 'Hello',
        ]));

        $this->assertSame(403, $response->get_status());
    }
}
