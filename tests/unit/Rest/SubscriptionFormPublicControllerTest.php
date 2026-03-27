<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Auth\CaptchaGuard;
use WSms\Auth\RateLimiter;
use WSms\Rest\SubscriptionFormPublicController;
use WSms\SubscriptionForm\SubscriptionForm;
use WSms\SubscriptionForm\SubscriptionFormRepository;
use WSms\SubscriptionForm\SubscriptionHandler;
use WSms\SubscriptionForm\SubmissionResult;

class SubscriptionFormPublicControllerTest extends TestCase
{
    private SubscriptionFormPublicController $controller;
    private MockObject&SubscriptionFormRepository $formRepository;
    private MockObject&SubscriptionHandler $handler;
    private MockObject&RateLimiter $rateLimiter;
    private MockObject&CaptchaGuard $captchaGuard;

    protected function setUp(): void
    {
        $this->formRepository = $this->createMock(SubscriptionFormRepository::class);
        $this->handler = $this->createMock(SubscriptionHandler::class);
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->captchaGuard = $this->createMock(CaptchaGuard::class);

        $this->controller = new SubscriptionFormPublicController(
            $this->formRepository,
            $this->handler,
            $this->rateLimiter,
            $this->captchaGuard,
        );

        // Default: no rate limiting.
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => true, 'remaining' => 5, 'retry_after' => 0,
        ]);
    }

    private function makeRequest(array $params = [], ?string $captchaToken = null): \WP_REST_Request
    {
        $request = new \WP_REST_Request('POST', '/subscribe/test-form');
        $request->set_param('slug', 'test-form');

        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        if ($captchaToken) {
            $request->set_header('X-Captcha-Response', $captchaToken);
        }

        return $request;
    }

    private function mockActiveForm(): void
    {
        $form = $this->createMock(SubscriptionForm::class);
        $form->method('isActive')->willReturn(true);
        $this->formRepository->method('findBySlug')->willReturn($form);
    }

    public function testSubmitBlockedWhenCaptchaFails(): void
    {
        $this->captchaGuard->method('verify')->willReturn(false);

        $response = $this->controller->handleSubmit($this->makeRequest(['email' => 'test@example.com']));

        $this->assertSame(403, $response->get_status());
        $this->assertSame('captcha_failed', $response->get_data()['error']);
    }

    public function testSubmitAllowedWhenCaptchaDisabled(): void
    {
        $this->captchaGuard->method('verify')->willReturn(null);
        $this->mockActiveForm();

        $this->handler->method('submit')->willReturn(SubmissionResult::success());

        $response = $this->controller->handleSubmit($this->makeRequest(['email' => 'test@example.com']));

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    public function testSubmitAllowedWhenCaptchaVerified(): void
    {
        $this->captchaGuard->method('verify')->willReturn(true);
        $this->mockActiveForm();

        $this->handler->method('submit')->willReturn(SubmissionResult::success());

        $response = $this->controller->handleSubmit($this->makeRequest(
            ['email' => 'test@example.com'],
            'valid-captcha-token',
        ));

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    public function testCaptchaVerifiedWithSubscribeAction(): void
    {
        $this->captchaGuard->expects($this->once())
            ->method('verify')
            ->with($this->isInstanceOf(\WP_REST_Request::class), 'subscribe')
            ->willReturn(null);

        $this->mockActiveForm();

        $this->handler->method('submit')->willReturn(SubmissionResult::success());

        $this->controller->handleSubmit($this->makeRequest(['email' => 'test@example.com']));
    }
}
