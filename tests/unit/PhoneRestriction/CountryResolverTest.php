<?php

namespace Tests\Unit\PhoneRestriction;

use PHPUnit\Framework\TestCase;
use WSms\PhoneRestriction\CountryResolver;

class CountryResolverTest extends TestCase
{
    private CountryResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CountryResolver();
    }

    /** @dataProvider unambiguousNumbersProvider */
    public function test_resolves_unambiguous_country_codes(string $phone, string $expected): void
    {
        $this->assertSame($expected, $this->resolver->resolveCountry($phone));
    }

    public static function unambiguousNumbersProvider(): array
    {
        return [
            'UK +44'       => ['+447911123456', 'GB'],
            'Germany +49'  => ['+4915112345678', 'DE'],
            'France +33'   => ['+33612345678', 'FR'],
            'Japan +81'    => ['+819012345678', 'JP'],
            'Australia +61'=> ['+61412345678', 'AU'],
            'Brazil +55'   => ['+5511912345678', 'BR'],
            'India +91'    => ['+919876543210', 'IN'],
        ];
    }

    public function test_shared_code_returns_primary_without_enhanced_db(): void
    {
        // +1 is shared by US, CA, and others — US is primary
        $this->assertSame('US', $this->resolver->resolveCountry('+12025551234'));
    }

    public function test_three_digit_country_code(): void
    {
        // +998 is Uzbekistan — must not match +99 or +9
        $this->assertSame('UZ', $this->resolver->resolveCountry('+998901234567'));
    }

    public function test_returns_null_for_non_e164_number(): void
    {
        $this->assertNull($this->resolver->resolveCountry('09121234567'));
    }

    public function test_returns_null_for_empty_string(): void
    {
        $this->assertNull($this->resolver->resolveCountry(''));
    }

    public function test_returns_null_for_invalid_characters(): void
    {
        $this->assertNull($this->resolver->resolveCountry('+abc'));
    }

    public function test_returns_null_for_plus_only(): void
    {
        $this->assertNull($this->resolver->resolveCountry('+'));
    }

    public function test_get_all_countries_returns_map(): void
    {
        $countries = $this->resolver->getAllCountries();

        $this->assertIsArray($countries);
        $this->assertArrayHasKey('US', $countries);
        $this->assertArrayHasKey('GB', $countries);
        $this->assertSame('United States', $countries['US']);
        $this->assertGreaterThan(200, count($countries));
    }

    public function test_get_number_type_returns_null_without_enhanced_db(): void
    {
        $this->assertNull($this->resolver->getNumberType('+12025551234'));
    }
}
