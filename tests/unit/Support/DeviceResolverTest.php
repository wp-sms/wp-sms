<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WSms\Support\DeviceResolver;

class DeviceResolverTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_SERVER['HTTP_USER_AGENT']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_USER_AGENT']);
    }

    // --- Null / empty ---

    public function test_returns_null_when_no_ua_header(): void
    {
        $this->assertNull(DeviceResolver::resolve());
    }

    public function test_returns_null_for_empty_ua(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = '';
        $this->assertNull(DeviceResolver::resolve());
    }

    public function test_returns_null_for_unrecognizable_ua(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'curl/7.68.0';
        $this->assertNull(DeviceResolver::resolve());
    }

    // --- Browser detection (order matters) ---

    #[DataProvider('browserProvider')]
    public function test_detects_browser(string $ua, string $expectedBrowser): void
    {
        $_SERVER['HTTP_USER_AGENT'] = $ua;
        $result = DeviceResolver::resolve();

        $this->assertNotNull($result);
        $this->assertStringStartsWith($expectedBrowser, $result);
    }

    public static function browserProvider(): array
    {
        return [
            'Facebook in-app (FBAN)' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/20A362 [FBAN/FBIOS;FBAV/380.0]',
                'Facebook',
            ],
            'Facebook in-app (FBAV only)' => [
                'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/120.0.0.0 Mobile Safari/537.36 FBAV/445.0',
                'Facebook',
            ],
            'Instagram' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/20A362 Instagram 270.0',
                'Instagram',
            ],
            'Firefox iOS (FxiOS)' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/120.0 Mobile/15E148 Safari/605.1.15',
                'Firefox',
            ],
            'Edge iOS (EdgiOS)' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) EdgiOS/120.0 Mobile/15E148 Safari/605.1.15',
                'Edge',
            ],
            'Chrome iOS (CriOS)' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0 Mobile/15E148 Safari/605.1.15',
                'Chrome',
            ],
            'Edge desktop (Edg/)' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
                'Edge',
            ],
            'Edge Android (EdgA/)' => [
                'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36 EdgA/120.0',
                'Edge',
            ],
            'Opera desktop (OPR/)' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 OPR/106.0',
                'Opera',
            ],
            'Opera mobile (OPT/)' => [
                'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36 OPT/8.0',
                'Opera',
            ],
            'Samsung Internet' => [
                'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/115.0.0.0 Mobile Safari/537.36',
                'Samsung Internet',
            ],
            'Yandex' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 YaBrowser/24.1 Safari/537.36',
                'Yandex',
            ],
            'Vivaldi' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Vivaldi/6.5',
                'Vivaldi',
            ],
            'UC Browser' => [
                'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/120.0.0.0 UCBrowser/16.0 Mobile Safari/537.36',
                'UC Browser',
            ],
            'Chrome desktop' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Chrome',
            ],
            'Chrome Android' => [
                'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
                'Chrome',
            ],
            'Firefox desktop' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
                'Firefox',
            ],
            'Safari macOS' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2.1 Safari/605.1.15',
                'Safari',
            ],
            'IE 11 (Trident)' => [
                'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko',
                'IE',
            ],
            'IE legacy (MSIE)' => [
                'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)',
                'IE',
            ],
        ];
    }

    // --- Edge cases: fork detection priority ---

    public function test_edge_wins_over_chrome(): void
    {
        // Edge UA contains both "Chrome" and "Edg/"
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0';
        $this->assertSame('Edge on Windows', DeviceResolver::resolve());
    }

    public function test_opera_wins_over_chrome(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 OPR/106.0';
        $this->assertSame('Opera on Windows', DeviceResolver::resolve());
    }

    public function test_brave_detected_as_chrome(): void
    {
        // Brave is indistinguishable from Chrome in the UA string
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $this->assertSame('Chrome on Windows', DeviceResolver::resolve());
    }

    public function test_safari_not_matched_when_chrome_present(): void
    {
        // Chrome UA contains "Safari" but not "Version/" — should detect Chrome, not Safari
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $this->assertSame('Chrome on macOS', DeviceResolver::resolve());
    }

    // --- OS detection ---

    #[DataProvider('osProvider')]
    public function test_detects_os(string $ua, string $expectedOs): void
    {
        $_SERVER['HTTP_USER_AGENT'] = $ua;
        $result = DeviceResolver::resolve();

        $this->assertNotNull($result);
        $this->assertStringEndsWith($expectedOs, $result);
    }

    public static function osProvider(): array
    {
        return [
            'iOS (iPhone)' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0 Mobile/15E148 Safari/605.1.15',
                'iOS',
            ],
            'iOS (iPad)' => [
                'Mozilla/5.0 (iPad; CPU OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0 Mobile/15E148 Safari/605.1.15',
                'iOS',
            ],
            'iOS (iPod)' => [
                'Mozilla/5.0 (iPod touch; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0 Mobile/15E148 Safari/605.1.15',
                'iOS',
            ],
            'Android' => [
                'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
                'Android',
            ],
            'Chrome OS' => [
                'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Chrome OS',
            ],
            'Windows Phone' => [
                'Mozilla/5.0 (Windows Phone 10.0; Android 6.0.1; Microsoft; Lumia 950) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0 Mobile Safari/537.36 Edge/15.0',
                'Windows Phone',
            ],
            'Windows' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Windows',
            ],
            'macOS (Macintosh)' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2.1 Safari/605.1.15',
                'macOS',
            ],
            'macOS (Mac OS X)' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'macOS',
            ],
            'Linux' => [
                'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
                'Linux',
            ],
        ];
    }

    // --- OS priority edge cases ---

    public function test_ios_wins_over_mac_os_x(): void
    {
        // iPhone UA contains "Mac OS X" but should detect iOS, not macOS
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1';
        $result = DeviceResolver::resolve();

        $this->assertStringEndsWith('iOS', $result);
        $this->assertStringNotContainsString('macOS', $result);
    }

    public function test_android_wins_over_linux(): void
    {
        // Android UA contains "Linux" but should detect Android
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';
        $result = DeviceResolver::resolve();

        $this->assertStringEndsWith('Android', $result);
    }

    public function test_windows_phone_wins_over_windows(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows Phone 10.0; Android 6.0.1; Microsoft; Lumia 950) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0 Mobile Safari/537.36 Edge/15.0';
        $result = DeviceResolver::resolve();

        $this->assertStringEndsWith('Windows Phone', $result);
    }

    // --- Combined output format ---

    public function test_format_is_browser_on_os(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $this->assertSame('Chrome on Windows', DeviceResolver::resolve());
    }

    public function test_returns_browser_only_when_os_unrecognizable(): void
    {
        // FreeBSD — no OS match, but Firefox matches
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; FreeBSD amd64; rv:121.0) Gecko/20100101 Firefox/121.0';
        $this->assertSame('Firefox', DeviceResolver::resolve());
    }

    public function test_returns_os_only_when_browser_unrecognizable(): void
    {
        // Bot with Windows in the UA but no known browser token
        $_SERVER['HTTP_USER_AGENT'] = 'SomeBot/1.0 (Windows NT 10.0)';
        $this->assertSame('Windows', DeviceResolver::resolve());
    }
}
