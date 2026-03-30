<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WSms\Exception\ValidationException;
use WSms\Support\PhoneValidator;

class PhoneValidatorTest extends TestCase
{
    // --- toE164 ---

    #[DataProvider('validE164Provider')]
    public function test_toE164_returns_cleaned_phone_for_valid_input(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneValidator::toE164($input));
    }

    public static function validE164Provider(): array
    {
        return [
            'simple US'            => ['+12025551234', '+12025551234'],
            'simple UK'            => ['+447911123456', '+447911123456'],
            'single digit CC'      => ['+11234567890', '+11234567890'],
            'minimum length'       => ['+11', '+11'],
            'maximum length'       => ['+123456789012345', '+123456789012345'],
            'with spaces'          => ['+1 202 555 1234', '+12025551234'],
            'with hyphens'         => ['+1-202-555-1234', '+12025551234'],
            'with parens'          => ['+1 (202) 555-1234', '+12025551234'],
            'with dots'            => ['+1.202.555.1234', '+12025551234'],
            'mixed formatting'     => ['+1 (202) 555.1234', '+12025551234'],
        ];
    }

    #[DataProvider('invalidE164Provider')]
    public function test_toE164_returns_null_for_invalid_input(string $input): void
    {
        $this->assertNull(PhoneValidator::toE164($input));
    }

    public static function invalidE164Provider(): array
    {
        return [
            'no plus'               => ['12025551234'],
            'leading zero CC'       => ['+02025551234'],
            'too short'             => ['+1'],
            'too long'              => ['+1234567890123456'],
            'contains letters'      => ['+1202abc1234'],
            'empty string'          => [''],
            'just plus'             => ['+'],
            'local format'          => ['2025551234'],
            'national with trunk'   => ['02025551234'],
        ];
    }

    // --- isE164 ---

    public function test_isE164_returns_true_for_valid(): void
    {
        $this->assertTrue(PhoneValidator::isE164('+12025551234'));
    }

    public function test_isE164_returns_false_for_invalid(): void
    {
        $this->assertFalse(PhoneValidator::isE164('2025551234'));
    }

    public function test_isE164_returns_true_with_formatting(): void
    {
        $this->assertTrue(PhoneValidator::isE164('+1 (202) 555-1234'));
    }

    // --- assertE164 ---

    public function test_assertE164_returns_cleaned_phone_for_valid(): void
    {
        $this->assertSame('+12025551234', PhoneValidator::assertE164('+1 (202) 555-1234'));
    }

    public function test_assertE164_throws_for_invalid(): void
    {
        $this->expectException(ValidationException::class);
        PhoneValidator::assertE164('2025551234');
    }

    public function test_assertE164_exception_has_phone_field_error(): void
    {
        try {
            PhoneValidator::assertE164('invalid');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('phone', $e->getErrors());
        }
    }

    // --- E164_PATTERN constant ---

    public function test_constant_matches_valid_e164(): void
    {
        $this->assertMatchesRegularExpression(PhoneValidator::E164_PATTERN, '+12025551234');
    }

    public function test_constant_rejects_no_plus(): void
    {
        $this->assertDoesNotMatchRegularExpression(PhoneValidator::E164_PATTERN, '12025551234');
    }
}
