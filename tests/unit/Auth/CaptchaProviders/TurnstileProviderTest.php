<?php

namespace WSms\Tests\Unit\Auth\CaptchaProviders;

use PHPUnit\Framework\TestCase;
use WSms\Auth\CaptchaProviders\TurnstileProvider;

class TurnstileProviderTest extends TestCase
{
    private TurnstileProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new TurnstileProvider();
        $GLOBALS['_test_wp_remote_post'] = null;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wp_remote_post']);
    }

    public function testVerifyReturnsTrueOnSuccess(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['success' => true]),
        ];

        $result = $this->provider->verify('valid-token', 'secret', '1.2.3.4');

        $this->assertTrue($result);
    }

    public function testVerifyReturnsFalseOnFailure(): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body' => json_encode(['success' => false, 'error-codes' => ['invalid-input-response']]),
        ];

        $result = $this->provider->verify('invalid-token', 'secret', '1.2.3.4');

        $this->assertFalse($result);
    }

    public function testVerifyReturnsFalseOnWpError(): void
    {
        $GLOBALS['_test_wp_remote_post'] = new \WP_Error('http_request_failed', 'Connection timeout');

        $result = $this->provider->verify('token', 'secret', '1.2.3.4');

        $this->assertFalse($result);
    }

    public function testGetScriptUrlReturnsValidUrl(): void
    {
        $url = $this->provider->getScriptUrl();

        $this->assertStringContainsString('challenges.cloudflare.com', $url);
        $this->assertStringContainsString('render=explicit', $url);
    }
}
