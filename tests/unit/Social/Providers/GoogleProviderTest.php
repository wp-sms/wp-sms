<?php

namespace WSms\Tests\Unit\Social\Providers;

use PHPUnit\Framework\TestCase;
use WSms\Social\Providers\GoogleProvider;

class GoogleProviderTest extends TestCase
{
    private GoogleProvider $provider;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [
            'wsms_auth_settings' => [
                'social' => [
                    'google' => [
                        'enabled'       => true,
                        'client_id'     => 'test-client-id',
                        'client_secret' => 'test-client-secret',
                    ],
                ],
            ],
        ];

        $this->provider = new GoogleProvider();
    }

    public function testGetId(): void
    {
        $this->assertSame('google', $this->provider->getId());
    }

    public function testGetName(): void
    {
        $this->assertSame('Google', $this->provider->getName());
    }

    public function testIsTrustedEmailProvider(): void
    {
        $this->assertTrue($this->provider->isTrustedEmailProvider());
    }

    public function testGetIconSvgReturnsSvg(): void
    {
        $svg = $this->provider->getIconSvg();

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('#4285F4', $svg);
    }

    public function testCreateAuthorizationUrlContainsRequiredParams(): void
    {
        $result = $this->provider->createAuthorizationURL(
            'https://example.com/callback',
            'test-state-123',
            'test-code-verifier',
        );

        $this->assertArrayHasKey('url', $result);
        $url = $result['url'];

        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
        $this->assertStringContainsString('state=test-state-123', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('scope=openid+email+profile', $url);
        $this->assertStringContainsString('code_challenge=', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
    }

    public function testCreateAuthorizationUrlWithoutPkce(): void
    {
        $result = $this->provider->createAuthorizationURL(
            'https://example.com/callback',
            'test-state',
            null,
        );

        $this->assertStringNotContainsString('code_challenge=', $result['url']);
    }

    public function testExchangeCodeThrowsOnWpError(): void
    {
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_error', 'Connection failed');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token exchange failed: Connection failed');

        $this->provider->exchangeCode('auth-code', 'https://example.com/callback', 'verifier');
    }

    public function testExchangeCodeThrowsOnMissingAccessToken(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['error' => 'invalid_grant', 'error_description' => 'Code expired']),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Code expired');

        $this->provider->exchangeCode('bad-code', 'https://example.com/callback');
    }

    public function testExchangeCodeReturnsTokenData(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode([
                'access_token'  => 'ya29.abc',
                'refresh_token' => '1//xyz',
                'expires_in'    => 3600,
                'token_type'    => 'Bearer',
            ]),
        ];

        $result = $this->provider->exchangeCode('valid-code', 'https://example.com/callback');

        $this->assertSame('ya29.abc', $result['access_token']);
        $this->assertSame('1//xyz', $result['refresh_token']);
    }

    public function testGetUserInfoThrowsOnWpError(): void
    {
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_error', 'Timeout');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User info request failed: Timeout');

        $this->provider->getUserInfo('access-token');
    }

    public function testGetUserInfoThrowsOnInvalidResponse(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body' => json_encode(['error' => 'invalid_token']),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid user info response');

        $this->provider->getUserInfo('bad-token');
    }

    public function testGetUserInfoReturnsNormalizedData(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body' => json_encode([
                'id'             => '123456789',
                'email'          => 'user@gmail.com',
                'name'           => 'John Doe',
                'verified_email' => true,
                'given_name'     => 'John',
                'family_name'    => 'Doe',
            ]),
        ];

        $result = $this->provider->getUserInfo('valid-token');

        $this->assertSame('123456789', $result['id']);
        $this->assertSame('user@gmail.com', $result['email']);
        $this->assertSame('John Doe', $result['name']);
        $this->assertTrue($result['email_verified']);
        $this->assertSame('John', $result['given_name']);
        $this->assertSame('Doe', $result['family_name']);
    }
}
