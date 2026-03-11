<?php

namespace WSms\Tests\Unit\Mfa;

use PHPUnit\Framework\TestCase;
use WSms\Mfa\OtpGenerator;

class OtpGeneratorTest extends TestCase
{
    private OtpGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new OtpGenerator();
    }

    /**
     * @dataProvider codeLengthProvider
     */
    public function testGenerateReturnsCorrectLength(int $length): void
    {
        $code = $this->generator->generate($length);

        $this->assertSame($length, strlen($code));
    }

    public static function codeLengthProvider(): array
    {
        return [
            '4 digits' => [4],
            '6 digits' => [6],
            '8 digits' => [8],
        ];
    }

    public function testGenerateReturnsNumericOnly(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $code = $this->generator->generate(6);
            $this->assertMatchesRegularExpression('/^\d+$/', $code);
        }
    }

    public function testGenerateTokenReturnsHexString(): void
    {
        $token = $this->generator->generateToken(16);

        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    public function testGenerateTokenReturnsCorrectLength(): void
    {
        // 32 bytes = 64 hex characters
        $token = $this->generator->generateToken(32);
        $this->assertSame(64, strlen($token));

        // 16 bytes = 32 hex characters
        $token = $this->generator->generateToken(16);
        $this->assertSame(32, strlen($token));
    }

    public function testHashReturnsConsistentHash(): void
    {
        $code = '123456';
        $hash1 = $this->generator->hash($code);
        $hash2 = $this->generator->hash($code);

        $this->assertSame($hash1, $hash2);
        $this->assertSame(64, strlen($hash1)); // SHA-256 = 64 hex chars
    }

    public function testVerifyReturnsTrueForMatchingCode(): void
    {
        $code = '123456';
        $hash = $this->generator->hash($code);

        $this->assertTrue($this->generator->verify($code, $hash));
    }

    public function testVerifyReturnsFalseForWrongCode(): void
    {
        $code = '123456';
        $hash = $this->generator->hash($code);

        $this->assertFalse($this->generator->verify('654321', $hash));
    }
}
