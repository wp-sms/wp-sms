<?php

namespace WSms\Tests\Unit\Social\Oidc;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Social\Oidc\JwtValidator;
use WSms\Social\Oidc\OidcConfig;
use WSms\Social\Oidc\OidcDiscovery;
use WSms\Social\Oidc\OidcProvider;

class OidcProviderTest extends TestCase
{
    private OidcProvider $provider;
    private MockObject&OidcDiscovery $discovery;
    private MockObject&JwtValidator $jwtValidator;

    private array $discoveryDoc = [
        'issuer'                                 => 'https://oauth.telegram.org',
        'authorization_endpoint'                 => 'https://oauth.telegram.org/auth',
        'token_endpoint'                         => 'https://oauth.telegram.org/token',
        'jwks_uri'                               => 'https://oauth.telegram.org/.well-known/jwks.json',
        'token_endpoint_auth_methods_supported'  => ['client_secret_basic'],
    ];

    protected function setUp(): void
    {
        $this->discovery = $this->createMock(OidcDiscovery::class);
        $this->jwtValidator = $this->createMock(JwtValidator::class);

        $config = new OidcConfig(
            id: 'telegram',
            name: 'Telegram',
            discoveryUrl: 'https://oauth.telegram.org/.well-known/openid-configuration',
            clientId: 'test-bot-id',
            clientSecret: 'test-bot-secret',
            scopes: ['openid', 'profile', 'phone'],
            tokenAuthMethod: 'basic',
            isTrustedEmail: false,
            iconSvg: '<svg></svg>',
        );

        $this->provider = new OidcProvider($config, $this->discovery, $this->jwtValidator);

        unset($GLOBALS['_test_wp_remote_post']);
    }

    public function testGetIdReturnsTelegram(): void
    {
        $this->assertSame('telegram', $this->provider->getId());
    }

    public function testGetNameReturnsTelegram(): void
    {
        $this->assertSame('Telegram', $this->provider->getName());
    }

    public function testIsTrustedEmailProviderReturnsFalse(): void
    {
        $this->assertFalse($this->provider->isTrustedEmailProvider());
    }

    public function testGetIconSvgReturnsSvg(): void
    {
        $this->assertStringContainsString('<svg', $this->provider->getIconSvg());
    }

    public function testCreateAuthorizationUrlContainsRequiredParams(): void
    {
        $this->discovery->method('fetch')->willReturn($this->discoveryDoc);

        $result = $this->provider->createAuthorizationURL(
            'https://example.com/callback',
            'test-state',
            'test-verifier',
        );

        $url = $result['url'];
        $this->assertStringContainsString('oauth.telegram.org/auth', $url);
        $this->assertStringContainsString('client_id=test-bot-id', $url);
        $this->assertStringContainsString('state=test-state', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('scope=openid+profile+phone', $url);
        $this->assertStringContainsString('code_challenge=', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
    }

    public function testCreateAuthorizationUrlWithoutPkce(): void
    {
        $this->discovery->method('fetch')->willReturn($this->discoveryDoc);

        $result = $this->provider->createAuthorizationURL(
            'https://example.com/callback',
            'state',
            null,
        );

        $this->assertStringNotContainsString('code_challenge=', $result['url']);
    }

    public function testExchangeCodeThrowsOnWpError(): void
    {
        $this->discovery->method('fetch')->willReturn($this->discoveryDoc);
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_error', 'Connection failed');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token exchange failed: Connection failed');

        $this->provider->exchangeCode('auth-code', 'https://example.com/callback');
    }

    public function testExchangeCodeThrowsOnMissingAccessToken(): void
    {
        $this->discovery->method('fetch')->willReturn($this->discoveryDoc);
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['error' => 'invalid_grant', 'error_description' => 'Code expired']),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Code expired');

        $this->provider->exchangeCode('bad-code', 'https://example.com/callback');
    }

    public function testExchangeCodeReturnsTokensAndStoresIdToken(): void
    {
        $this->discovery->method('fetch')->willReturn($this->discoveryDoc);

        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode([
                'access_token' => 'test-access-token',
                'token_type'   => 'Bearer',
                'expires_in'   => 3600,
                'id_token'     => 'header.payload.signature',
            ]),
        ];

        $result = $this->provider->exchangeCode('valid-code', 'https://example.com/callback', 'verifier');

        $this->assertSame('test-access-token', $result['access_token']);
        $this->assertSame('header.payload.signature', $result['id_token']);
    }

    public function testGetUserInfoThrowsWithoutPriorExchangeCode(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No id_token available');

        $this->provider->getUserInfo('access-token');
    }

    public function testGetUserInfoReturnsNormalizedData(): void
    {
        $this->discovery->method('fetch')->willReturn($this->discoveryDoc);

        // First exchange code to store the id_token.
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode([
                'access_token' => 'at',
                'id_token'     => 'fake.jwt.token',
            ]),
        ];
        $this->provider->exchangeCode('code', 'https://example.com/callback');

        // Mock JWT validation to return Telegram-like payload.
        $this->jwtValidator->method('validate')->willReturn([
            'sub'                => '1234123412341234123',
            'id'                 => 987654321,
            'name'               => 'John Doe',
            'preferred_username' => 'johndoe',
            'picture'            => 'https://cdn.telegram.org/photo.jpg',
            'phone_number'       => '971577777777',
        ]);

        $userInfo = $this->provider->getUserInfo('at');

        $this->assertSame('1234123412341234123', $userInfo['id']);
        $this->assertSame('John Doe', $userInfo['name']);
        $this->assertSame('johndoe', $userInfo['preferred_username']);
        $this->assertSame('https://cdn.telegram.org/photo.jpg', $userInfo['picture']);
        $this->assertSame('', $userInfo['email']);
        $this->assertFalse($userInfo['email_verified']);
    }

    public function testGetUserInfoNormalizesPhoneToE164(): void
    {
        $this->discovery->method('fetch')->willReturn($this->discoveryDoc);

        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['access_token' => 'at', 'id_token' => 'fake.jwt']),
        ];
        $this->provider->exchangeCode('code', 'https://example.com/callback');

        $this->jwtValidator->method('validate')->willReturn([
            'sub'          => '123',
            'phone_number' => '971577777777', // No '+' prefix from Telegram.
        ]);

        $userInfo = $this->provider->getUserInfo('at');

        $this->assertSame('+971577777777', $userInfo['phone_number']);
    }

    public function testGetUserInfoPreservesExistingPlusPrefix(): void
    {
        $this->discovery->method('fetch')->willReturn($this->discoveryDoc);

        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['access_token' => 'at', 'id_token' => 'fake.jwt']),
        ];
        $this->provider->exchangeCode('code', 'https://example.com/callback');

        $this->jwtValidator->method('validate')->willReturn([
            'sub'          => '123',
            'phone_number' => '+44123456789',
        ]);

        $userInfo = $this->provider->getUserInfo('at');

        $this->assertSame('+44123456789', $userInfo['phone_number']);
    }

    public function testGetUserInfoHandlesNullPhone(): void
    {
        $this->discovery->method('fetch')->willReturn($this->discoveryDoc);

        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['access_token' => 'at', 'id_token' => 'fake.jwt']),
        ];
        $this->provider->exchangeCode('code', 'https://example.com/callback');

        $this->jwtValidator->method('validate')->willReturn([
            'sub'  => '123',
            'name' => 'Test',
        ]);

        $userInfo = $this->provider->getUserInfo('at');

        $this->assertNull($userInfo['phone_number']);
    }

    public function testTokenAuthMethodAutoDetectsFromDiscovery(): void
    {
        // Create provider without explicit tokenAuthMethod.
        $config = new OidcConfig(
            id: 'custom',
            name: 'Custom',
            discoveryUrl: 'https://custom.example.com/.well-known/openid-configuration',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            tokenAuthMethod: null, // Auto-detect.
        );

        $provider = new OidcProvider($config, $this->discovery, $this->jwtValidator);

        // Discovery says only 'client_secret_post' is supported.
        $doc = $this->discoveryDoc;
        $doc['token_endpoint_auth_methods_supported'] = ['client_secret_post'];
        $this->discovery->method('fetch')->willReturn($doc);

        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['access_token' => 'at', 'id_token' => 'jwt']),
        ];

        // Should not throw — means it correctly used POST body auth.
        $result = $provider->exchangeCode('code', 'https://example.com/callback');
        $this->assertSame('at', $result['access_token']);
    }
}
