<?php

namespace Tests\Unit\Verification;

use PHPUnit\Framework\TestCase;
use WSms\Verification\OtpGenerator;

class OtpGeneratorTest extends TestCase
{
    private OtpGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new OtpGenerator();
    }

    public function test_generate_returns_correct_length(): void
    {
        $code = $this->generator->generate(6);
        $this->assertSame(6, strlen($code));

        $code = $this->generator->generate(4);
        $this->assertSame(4, strlen($code));
    }

    public function test_generate_returns_only_digits(): void
    {
        $code = $this->generator->generate(6);
        $this->assertMatchesRegularExpression('/^[0-9]+$/', $code);
    }

    public function test_hash_returns_sha256(): void
    {
        $hash = $this->generator->hash('123456');
        $this->assertSame(hash('sha256', '123456'), $hash);
    }

    public function test_verify_with_correct_code(): void
    {
        $code = '123456';
        $hash = $this->generator->hash($code);
        $this->assertTrue($this->generator->verify($code, $hash));
    }

    public function test_verify_with_wrong_code(): void
    {
        $hash = $this->generator->hash('123456');
        $this->assertFalse($this->generator->verify('654321', $hash));
    }

    /**
     * @dataProvider unicodeDigitProvider
     */
    public function test_normalize_digits(string $input, string $expected): void
    {
        $this->assertSame($expected, OtpGenerator::normalizeDigits($input));
    }

    public static function unicodeDigitProvider(): array
    {
        return [
            'ASCII pass-through'  => ['123456', '123456'],
            'Persian'             => ['۴۸۳۹۲۱', '483921'],
            'Arabic-Indic'        => ['٤٨٣٩٢١', '483921'],
            'Devanagari'          => ['४८३९२१', '483921'],
            'Thai'                => ['๔๘๓๙๒๑', '483921'],
            'Full-width'          => ['４８３９２１', '483921'],
            'Mixed scripts'       => ['12۳۴56', '123456'],
            'Empty string'        => ['', ''],
        ];
    }

    public function test_verify_succeeds_with_unicode_digits(): void
    {
        $code = '483921';
        $hash = $this->generator->hash($code);

        // Persian digits should verify against ASCII hash
        $this->assertTrue($this->generator->verify('۴۸۳۹۲۱', $hash));
    }

    public function test_verify_succeeds_with_arabic_indic_digits(): void
    {
        $code = '483921';
        $hash = $this->generator->hash($code);

        $this->assertTrue($this->generator->verify('٤٨٣٩٢١', $hash));
    }

    public function test_verify_succeeds_with_mixed_digits(): void
    {
        $code = '123456';
        $hash = $this->generator->hash($code);

        $this->assertTrue($this->generator->verify('12۳۴56', $hash));
    }

    public function test_generate_token_returns_hex(): void
    {
        $token = $this->generator->generateToken(16);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
    }
}
