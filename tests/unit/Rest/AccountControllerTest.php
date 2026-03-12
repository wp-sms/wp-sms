<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Auth\AccountManager;
use WSms\Auth\AuthSession;
use WSms\Auth\RateLimiter;
use WSms\Rest\AccountController;

class AccountControllerTest extends TestCase
{
    private AccountController $controller;
    private MockObject&AccountManager $accountManager;
    private MockObject&RateLimiter $rateLimiter;
    private MockObject&AuthSession $authSession;

    protected function setUp(): void
    {
        $this->accountManager = $this->createMock(AccountManager::class);
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->authSession = $this->createMock(AuthSession::class);

        $this->controller = new AccountController(
            $this->accountManager,
            $this->rateLimiter,
            $this->authSession,
        );

        // Default: no rate limiting.
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => true, 'remaining' => 5, 'retry_after' => 0,
        ]);

        unset($GLOBALS['_test_current_user_id']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_current_user_id']);
    }

    public function testRegisterDelegatesToAccountManager(): void
    {
        $this->accountManager->method('registerUser')->willReturn([
            'success' => true,
            'user_id' => 1,
            'message' => 'Registration successful.',
        ]);

        $request = new \WP_REST_Request('POST', '/auth/register');
        $request->set_param('email', 'test@example.com');
        $request->set_param('password', 'Pass123!');

        $response = $this->controller->handleRegister($request);

        $this->assertSame(201, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    public function testRegisterReturns400OnFailure(): void
    {
        $this->accountManager->method('registerUser')->willReturn([
            'success' => false,
            'error'   => 'missing_email',
            'message' => 'Email is required.',
        ]);

        $request = new \WP_REST_Request('POST', '/auth/register');
        $request->set_param('email', '');
        $request->set_param('password', 'Pass123!');

        $response = $this->controller->handleRegister($request);

        $this->assertSame(400, $response->get_status());
    }

    public function testRegisterRateLimited(): void
    {
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->rateLimiter->method('check')->willReturn([
            'allowed' => false, 'remaining' => 0, 'retry_after' => 45,
        ]);

        $controller = new AccountController($this->accountManager, $this->rateLimiter, $this->authSession);

        $request = new \WP_REST_Request('POST', '/auth/register');
        $request->set_param('email', 'test@example.com');
        $request->set_param('password', 'Pass123!');

        $response = $controller->handleRegister($request);

        $this->assertSame(429, $response->get_status());
    }

    public function testForgotPasswordAlwaysReturns200(): void
    {
        $this->accountManager->expects($this->once())->method('initiatePasswordReset');

        $request = new \WP_REST_Request('POST', '/auth/forgot-password');
        $request->set_param('email', 'test@example.com');

        $response = $this->controller->handleForgotPassword($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    public function testResetPasswordDelegatesToAccountManager(): void
    {
        $this->accountManager->method('completePasswordReset')->willReturn([
            'success' => true,
            'message' => 'Password has been reset successfully.',
        ]);

        $request = new \WP_REST_Request('POST', '/auth/reset-password');
        $request->set_param('token', 'some-token');
        $request->set_param('password', 'NewPass1!');

        $response = $this->controller->handleResetPassword($request);

        $this->assertSame(200, $response->get_status());
    }

    public function testVerifyEmailDelegatesToAccountManager(): void
    {
        $this->accountManager->method('verifyEmail')->willReturn([
            'success' => true,
            'message' => 'Email verified successfully.',
        ]);

        $request = new \WP_REST_Request('POST', '/auth/verify-email');
        $request->set_param('token', 'some-token');

        $response = $this->controller->handleVerifyEmail($request);

        $this->assertSame(200, $response->get_status());
    }

    public function testUpdateProfileDelegatesToAccountManager(): void
    {
        $GLOBALS['_test_current_user_id'] = 1;

        $this->accountManager->method('updateProfile')->willReturn([
            'success' => true,
            'message' => 'Profile updated.',
        ]);

        $request = new \WP_REST_Request('PUT', '/auth/profile');
        $request->set_param('display_name', 'New Name');

        $response = $this->controller->handleUpdateProfile($request);

        $this->assertSame(200, $response->get_status());
    }

    public function testChangePasswordDelegatesToAccountManager(): void
    {
        $GLOBALS['_test_current_user_id'] = 1;

        $this->accountManager->method('changePassword')->willReturn([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);

        $request = new \WP_REST_Request('PUT', '/auth/password');
        $request->set_param('current_password', 'old');
        $request->set_param('new_password', 'new');

        $response = $this->controller->handleChangePassword($request);

        $this->assertSame(200, $response->get_status());
    }

    public function testLogoutDelegatesToAccountManager(): void
    {
        $this->accountManager->expects($this->once())->method('logout');

        $request = new \WP_REST_Request('POST', '/auth/logout');

        $response = $this->controller->handleLogout($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    public function testCheckAuthenticatedReturnsFalseForGuest(): void
    {
        $GLOBALS['_test_current_user_id'] = 0;
        $request = new \WP_REST_Request();
        $this->assertFalse($this->controller->checkAuthenticated($request));
    }

    public function testCheckAuthenticatedReturnsTrueForLoggedIn(): void
    {
        $GLOBALS['_test_current_user_id'] = 1;
        $request = new \WP_REST_Request();
        $this->assertTrue($this->controller->checkAuthenticated($request));
    }
}
