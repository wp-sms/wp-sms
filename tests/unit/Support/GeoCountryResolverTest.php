<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use WSms\Support\GeoCountryResolver;

class GeoCountryResolverTest extends TestCase
{
    protected function setUp(): void
    {
        unset(
            $_SERVER['HTTP_CF_IPCOUNTRY'],
            $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'],
            $_SERVER['HTTP_X_COUNTRY_CODE'],
        );
    }

    protected function tearDown(): void
    {
        $this->setUp();
    }

    // --- resolve() ---

    public function test_resolve_returns_null_when_no_header(): void
    {
        $this->assertNull(GeoCountryResolver::resolve());
    }

    public function test_resolve_returns_uppercase_country_from_cf_header(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'DE';
        $this->assertSame('DE', GeoCountryResolver::resolve());
    }

    public function test_resolve_returns_uppercase_country_from_cloudfront_header(): void
    {
        $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] = 'GB';
        $this->assertSame('GB', GeoCountryResolver::resolve());
    }

    public function test_resolve_returns_uppercase_country_from_x_country_code_header(): void
    {
        $_SERVER['HTTP_X_COUNTRY_CODE'] = 'JP';
        $this->assertSame('JP', GeoCountryResolver::resolve());
    }

    public function test_resolve_prefers_cf_header_over_cloudfront(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';
        $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] = 'CA';
        $this->assertSame('US', GeoCountryResolver::resolve());
    }

    public function test_resolve_falls_back_to_cloudfront_when_cf_missing(): void
    {
        $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] = 'FR';
        $_SERVER['HTTP_X_COUNTRY_CODE'] = 'IT';
        $this->assertSame('FR', GeoCountryResolver::resolve());
    }

    public function test_resolve_returns_null_for_tor(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'T1';
        $this->assertNull(GeoCountryResolver::resolve());
    }

    public function test_resolve_returns_null_for_unknown(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'XX';
        $this->assertNull(GeoCountryResolver::resolve());
    }

    public function test_resolve_rejects_invalid_values(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'INVALID';
        $this->assertNull(GeoCountryResolver::resolve());
    }

    public function test_resolve_rejects_single_char(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'A';
        $this->assertNull(GeoCountryResolver::resolve());
    }

    public function test_resolve_rejects_numeric_values(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = '12';
        $this->assertNull(GeoCountryResolver::resolve());
    }

    public function test_resolve_rejects_empty_string(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = '';
        $this->assertNull(GeoCountryResolver::resolve());
    }

    public function test_resolve_trims_whitespace(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = ' US ';
        $this->assertSame('US', GeoCountryResolver::resolve());
    }

    public function test_resolve_normalizes_lowercase_input(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'de';
        $this->assertSame('DE', GeoCountryResolver::resolve());
    }

    // --- resolveRaw() ---

    public function test_resolve_raw_returns_null_when_no_header(): void
    {
        $this->assertNull(GeoCountryResolver::resolveRaw());
    }

    public function test_resolve_raw_returns_uppercase_country(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'de';
        $this->assertSame('DE', GeoCountryResolver::resolveRaw());
    }

    public function test_resolve_raw_preserves_tor(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'T1';
        $this->assertSame('T1', GeoCountryResolver::resolveRaw());
    }

    public function test_resolve_raw_preserves_unknown(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'XX';
        $this->assertSame('XX', GeoCountryResolver::resolveRaw());
    }

    public function test_resolve_raw_rejects_invalid_values(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = '<script>';
        $this->assertNull(GeoCountryResolver::resolveRaw());
    }
}
