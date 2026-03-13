<?php

namespace WSms\Tests\Unit\Social\Oidc;

use PHPUnit\Framework\TestCase;
use WSms\Social\Oidc\OidcDiscovery;

class OidcDiscoveryTest extends TestCase
{
    private OidcDiscovery $discovery;

    protected function setUp(): void
    {
        $this->discovery = new OidcDiscovery();
        unset($GLOBALS['_test_wp_remote_get'], $GLOBALS['_test_transients']);
    }

    public function testFetchRejectsHttpUrl(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Discovery URL must use HTTPS');

        $this->discovery->fetch('http://example.com/.well-known/openid-configuration');
    }

    public function testFetchThrowsOnWpError(): void
    {
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'Connection refused');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('discovery_not_found');

        $this->discovery->fetch('https://oauth.example.com/.well-known/openid-configuration');
    }

    public function testFetchThrowsOnTimeoutError(): void
    {
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('http_request_failed', 'Operation timed out');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('discovery_timeout');

        $this->discovery->fetch('https://oauth.example.com/.well-known/openid-configuration');
    }

    public function testFetchThrowsOnNon200Response(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => '',
            'response' => ['code' => 404],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 404');

        $this->discovery->fetch('https://oauth.example.com/.well-known/openid-configuration');
    }

    public function testFetchThrowsOnInvalidJson(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => 'not json',
            'response' => ['code' => 200],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $this->discovery->fetch('https://oauth.example.com/.well-known/openid-configuration');
    }

    public function testFetchThrowsOnMissingRequiredField(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode([
                'issuer'                 => 'https://oauth.example.com',
                'authorization_endpoint' => 'https://oauth.example.com/auth',
                // missing: token_endpoint, jwks_uri
            ]),
            'response' => ['code' => 200],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("missing_required_field");

        $this->discovery->fetch('https://oauth.example.com/.well-known/openid-configuration');
    }

    public function testFetchThrowsOnIssuerMismatch(): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode([
                'issuer'                 => 'https://other.example.com',
                'authorization_endpoint' => 'https://oauth.example.com/auth',
                'token_endpoint'         => 'https://oauth.example.com/token',
                'jwks_uri'               => 'https://oauth.example.com/.well-known/jwks.json',
            ]),
            'response' => ['code' => 200],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('issuer_mismatch');

        $this->discovery->fetch('https://oauth.example.com/.well-known/openid-configuration');
    }

    public function testFetchReturnsValidDocument(): void
    {
        $doc = [
            'issuer'                 => 'https://oauth.example.com',
            'authorization_endpoint' => 'https://oauth.example.com/auth',
            'token_endpoint'         => 'https://oauth.example.com/token',
            'jwks_uri'               => 'https://oauth.example.com/.well-known/jwks.json',
        ];

        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode($doc),
            'response' => ['code' => 200],
        ];

        $result = $this->discovery->fetch('https://oauth.example.com/.well-known/openid-configuration');

        $this->assertSame('https://oauth.example.com', $result['issuer']);
        $this->assertSame('https://oauth.example.com/auth', $result['authorization_endpoint']);
        $this->assertSame('https://oauth.example.com/token', $result['token_endpoint']);
    }

    public function testFetchCachesResult(): void
    {
        $doc = [
            'issuer'                 => 'https://oauth.example.com',
            'authorization_endpoint' => 'https://oauth.example.com/auth',
            'token_endpoint'         => 'https://oauth.example.com/token',
            'jwks_uri'               => 'https://oauth.example.com/.well-known/jwks.json',
        ];

        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode($doc),
            'response' => ['code' => 200],
        ];

        $url = 'https://oauth.example.com/.well-known/openid-configuration';
        $this->discovery->fetch($url);

        // Second call should use cache — set HTTP to error to prove it.
        $GLOBALS['_test_wp_remote_get'] = new \WP_Error('should_not_be_called', '');

        $result = $this->discovery->fetch($url);

        $this->assertSame('https://oauth.example.com', $result['issuer']);
    }

    public function testFetchHandlesTrailingSlashInIssuer(): void
    {
        $doc = [
            'issuer'                 => 'https://oauth.example.com/',
            'authorization_endpoint' => 'https://oauth.example.com/auth',
            'token_endpoint'         => 'https://oauth.example.com/token',
            'jwks_uri'               => 'https://oauth.example.com/.well-known/jwks.json',
        ];

        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode($doc),
            'response' => ['code' => 200],
        ];

        $result = $this->discovery->fetch('https://oauth.example.com/.well-known/openid-configuration');

        $this->assertSame('https://oauth.example.com/', $result['issuer']);
    }
}
