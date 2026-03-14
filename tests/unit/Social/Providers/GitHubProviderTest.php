<?php

namespace WSms\Tests\Unit\Social\Providers;

use PHPUnit\Framework\TestCase;
use WSms\Social\Providers\GitHubProvider;

class GitHubProviderTest extends TestCase
{
    private GitHubProvider $provider;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [
            'wsms_auth_settings' => [
                'social' => [
                    'github' => [
                        'enabled'       => true,
                        'client_id'     => 'test-client-id',
                        'client_secret' => 'test-client-secret',
                    ],
                ],
            ],
        ];

        $this->provider = new GitHubProvider();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_options'],
            $GLOBALS['_test_wp_remote_get'],
            $GLOBALS['_test_wp_remote_post'],
        );
    }

    public function testGetId(): void
    {
        $this->assertSame('github', $this->provider->getId());
    }

    public function testGetName(): void
    {
        $this->assertSame('GitHub', $this->provider->getName());
    }

    public function testIsTrustedEmailProvider(): void
    {
        $this->assertTrue($this->provider->isTrustedEmailProvider());
    }

    public function testGetIconSvgReturnsSvg(): void
    {
        $svg = $this->provider->getIconSvg();

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('currentColor', $svg);
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

        $this->assertStringContainsString('github.com/login/oauth/authorize', $url);
        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('state=test-state-123', $url);
        $this->assertStringContainsString('scope=read%3Auser+user%3Aemail', $url);
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
            'body' => json_encode(['error' => 'bad_verification_code', 'error_description' => 'The code passed is incorrect or expired.']),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The code passed is incorrect or expired.');

        $this->provider->exchangeCode('bad-code', 'https://example.com/callback');
    }

    public function testExchangeCodeReturnsTokenData(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode([
                'access_token' => 'gho_abc123',
                'token_type'   => 'bearer',
                'scope'        => 'read:user,user:email',
            ]),
        ];

        $result = $this->provider->exchangeCode('valid-code', 'https://example.com/callback');

        $this->assertSame('gho_abc123', $result['access_token']);
        $this->assertSame('bearer', $result['token_type']);
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
            'body' => json_encode(['message' => 'Bad credentials']),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid user info response from GitHub.');

        $this->provider->getUserInfo('bad-token');
    }

    public function testGetUserInfoReturnsNormalizedData(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body' => json_encode([
                'id'         => 12345678,
                'login'      => 'octocat',
                'name'       => 'The Octocat',
                'email'      => 'octocat@github.com',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/12345678',
            ]),
        ];

        $result = $this->provider->getUserInfo('valid-token');

        $this->assertSame('12345678', $result['id']);
        $this->assertSame('octocat@github.com', $result['email']);
        $this->assertSame('The Octocat', $result['name']);
        $this->assertTrue($result['email_verified']);
        $this->assertSame('https://avatars.githubusercontent.com/u/12345678', $result['picture']);
    }

    public function testGetUserInfoFetchesEmailFromEmailsEndpointWhenProfileEmailIsNull(): void
    {
        $GLOBALS['_test_wp_remote_get'] = function (string $url) {
            if (str_contains($url, '/user/emails')) {
                return [
                    'body' => json_encode([
                        ['email' => 'secondary@example.com', 'primary' => false, 'verified' => true],
                        ['email' => 'primary@example.com', 'primary' => true, 'verified' => true],
                    ]),
                ];
            }

            return [
                'body' => json_encode([
                    'id'         => 99999,
                    'login'      => 'privateuser',
                    'name'       => 'Private User',
                    'email'      => null,
                    'avatar_url' => 'https://avatars.githubusercontent.com/u/99999',
                ]),
            ];
        };

        $result = $this->provider->getUserInfo('valid-token');

        $this->assertSame('99999', $result['id']);
        $this->assertSame('primary@example.com', $result['email']);
        $this->assertSame('Private User', $result['name']);
        $this->assertTrue($result['email_verified']);
    }

    public function testGetUserInfoUsesLoginAsFallbackName(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body' => json_encode([
                'id'         => 55555,
                'login'      => 'noname-user',
                'name'       => null,
                'email'      => 'noname@example.com',
                'avatar_url' => null,
            ]),
        ];

        $result = $this->provider->getUserInfo('valid-token');

        $this->assertSame('noname-user', $result['name']);
        $this->assertSame('', $result['picture']);
    }

    public function testGetUserInfoEmailVerificationFromEmailsEndpoint(): void
    {
        $GLOBALS['_test_wp_remote_get'] = function (string $url) {
            if (str_contains($url, '/user/emails')) {
                return [
                    'body' => json_encode([
                        ['email' => 'unverified@example.com', 'primary' => true, 'verified' => false],
                    ]),
                ];
            }

            return [
                'body' => json_encode([
                    'id'    => 77777,
                    'login' => 'unverified-user',
                    'email' => null,
                ]),
            ];
        };

        $result = $this->provider->getUserInfo('valid-token');

        $this->assertSame('unverified@example.com', $result['email']);
        $this->assertFalse($result['email_verified']);
    }

    public function testGetUserInfoHandlesEmailsEndpointFailure(): void
    {
        $GLOBALS['_test_wp_remote_get'] = function (string $url) {
            if (str_contains($url, '/user/emails')) {
                return new \WP_Error('http_error', 'Forbidden');
            }

            return [
                'body' => json_encode([
                    'id'    => 88888,
                    'login' => 'noemail-user',
                    'email' => null,
                ]),
            ];
        };

        $result = $this->provider->getUserInfo('valid-token');

        $this->assertSame('88888', $result['id']);
        $this->assertSame('', $result['email']);
        $this->assertFalse($result['email_verified']);
    }
}
