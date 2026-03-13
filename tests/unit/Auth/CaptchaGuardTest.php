<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WSms\Auth\CaptchaGuard;
use WSms\Auth\CaptchaProviders\ProviderInterface;

class CaptchaGuardTest extends TestCase
{
    private CaptchaGuard $guard;
    private ProviderInterface $mockProvider;

    protected function setUp(): void
    {
        $this->mockProvider = $this->createMock(ProviderInterface::class);
        $this->guard = new CaptchaGuard(['turnstile' => $this->mockProvider]);
        $GLOBALS['_test_options'] = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_options'] = [];
        unset($_SERVER['REMOTE_ADDR']);
    }

    private function enableCaptcha(array $overrides = []): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'captcha' => array_merge([
                'enabled'           => true,
                'provider'          => 'turnstile',
                'site_key'          => 'test-site-key',
                'secret_key'        => 'test-secret-key',
                'protected_actions' => ['login', 'register', 'forgot_password'],
                'fail_open'         => false,
            ], $overrides),
        ];
    }

    private function makeRequest(?string $captchaToken = null): WP_REST_Request
    {
        $request = new WP_REST_Request('POST', '/test');
        if ($captchaToken) {
            $request->set_header('X-Captcha-Response', $captchaToken);
        }
        return $request;
    }

    // --- Disabled / not required ---

    public function testReturnsNullWhenCaptchaDisabled(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = ['captcha' => ['enabled' => false]];

        $result = $this->guard->verify($this->makeRequest(), 'login');

        $this->assertNull($result);
    }

    public function testReturnsNullWhenNoSettings(): void
    {
        $result = $this->guard->verify($this->makeRequest(), 'login');

        $this->assertNull($result);
    }

    public function testReturnsNullForUnprotectedAction(): void
    {
        $this->enableCaptcha();

        $result = $this->guard->verify($this->makeRequest('some-token'), 'verify');

        $this->assertNull($result);
    }

    // --- Token missing ---

    public function testReturnsFalseWhenTokenMissing(): void
    {
        $this->enableCaptcha();

        $result = $this->guard->verify($this->makeRequest(), 'login');

        $this->assertFalse($result);
    }

    // --- Provider verification ---

    public function testReturnsTrueWhenProviderVerifies(): void
    {
        $this->enableCaptcha();
        $this->mockProvider->method('verify')->willReturn(true);

        $result = $this->guard->verify($this->makeRequest('valid-token'), 'login');

        $this->assertTrue($result);
    }

    public function testReturnsFalseWhenProviderRejects(): void
    {
        $this->enableCaptcha();
        $this->mockProvider->method('verify')->willReturn(false);

        $result = $this->guard->verify($this->makeRequest('invalid-token'), 'login');

        $this->assertFalse($result);
    }

    public function testPassesCorrectParametersToProvider(): void
    {
        $this->enableCaptcha();

        $this->mockProvider->expects($this->once())
            ->method('verify')
            ->with('my-captcha-token', 'test-secret-key', '127.0.0.1')
            ->willReturn(true);

        $this->guard->verify($this->makeRequest('my-captcha-token'), 'login');
    }

    // --- Protected actions ---

    public function testVerifiesRegisterAction(): void
    {
        $this->enableCaptcha();
        $this->mockProvider->method('verify')->willReturn(true);

        $result = $this->guard->verify($this->makeRequest('token'), 'register');

        $this->assertTrue($result);
    }

    public function testVerifiesForgotPasswordAction(): void
    {
        $this->enableCaptcha();
        $this->mockProvider->method('verify')->willReturn(true);

        $result = $this->guard->verify($this->makeRequest('token'), 'forgot_password');

        $this->assertTrue($result);
    }

    public function testCustomProtectedActions(): void
    {
        $this->enableCaptcha(['protected_actions' => ['identify']]);

        $this->mockProvider->method('verify')->willReturn(true);

        // 'identify' is protected — should verify.
        $result = $this->guard->verify($this->makeRequest('token'), 'identify');
        $this->assertTrue($result);

        // 'login' is not protected — should return null.
        $result = $this->guard->verify($this->makeRequest('token'), 'login');
        $this->assertNull($result);
    }

    // --- Fail open / closed ---

    public function testFailClosedWhenSecretKeyMissing(): void
    {
        $this->enableCaptcha(['secret_key' => '', 'fail_open' => false]);

        $result = $this->guard->verify($this->makeRequest('token'), 'login');

        $this->assertFalse($result);
    }

    public function testFailOpenWhenSecretKeyMissing(): void
    {
        $this->enableCaptcha(['secret_key' => '', 'fail_open' => true]);

        $result = $this->guard->verify($this->makeRequest('token'), 'login');

        $this->assertNull($result);
    }

    public function testFailClosedWhenProviderThrows(): void
    {
        $this->enableCaptcha(['fail_open' => false]);
        $this->mockProvider->method('verify')->willThrowException(new \RuntimeException('Network error'));

        $result = $this->guard->verify($this->makeRequest('token'), 'login');

        $this->assertFalse($result);
    }

    public function testFailOpenWhenProviderThrows(): void
    {
        $this->enableCaptcha(['fail_open' => true]);
        $this->mockProvider->method('verify')->willThrowException(new \RuntimeException('Network error'));

        $result = $this->guard->verify($this->makeRequest('token'), 'login');

        $this->assertNull($result);
    }

    public function testFailClosedWhenProviderNotRegistered(): void
    {
        $this->enableCaptcha(['provider' => 'unknown', 'fail_open' => false]);

        $result = $this->guard->verify($this->makeRequest('token'), 'login');

        $this->assertFalse($result);
    }

    public function testFailOpenWhenProviderNotRegistered(): void
    {
        $this->enableCaptcha(['provider' => 'unknown', 'fail_open' => true]);

        $result = $this->guard->verify($this->makeRequest('token'), 'login');

        $this->assertNull($result);
    }

    // --- getPublicConfig ---

    public function testGetPublicConfigWhenDisabled(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = ['captcha' => ['enabled' => false]];

        $config = $this->guard->getPublicConfig();

        $this->assertNull($config);
    }

    public function testGetPublicConfigWhenEnabled(): void
    {
        $this->enableCaptcha();

        $config = $this->guard->getPublicConfig();

        $this->assertSame(true, $config['enabled']);
        $this->assertSame('turnstile', $config['provider']);
        $this->assertSame('test-site-key', $config['site_key']);
        $this->assertSame(['login', 'register', 'forgot_password'], $config['protected_actions']);
        // Secret key must NOT be exposed.
        $this->assertArrayNotHasKey('secret_key', $config);
    }
}
