<?php

namespace Tests\Unit\PhoneRestriction;

use PHPUnit\Framework\TestCase;
use WSms\PhoneRestriction\RestrictionResult;

class RestrictionResultTest extends TestCase
{
    public function test_allowed_result(): void
    {
        $result = RestrictionResult::allowed('US');

        $this->assertTrue($result->allowed);
        $this->assertNull($result->reason);
        $this->assertNull($result->message);
        $this->assertSame('US', $result->country);
        $this->assertNull($result->numberType);
    }

    public function test_allowed_without_country(): void
    {
        $result = RestrictionResult::allowed();

        $this->assertTrue($result->allowed);
        $this->assertNull($result->country);
    }

    public function test_blocked_result(): void
    {
        $result = RestrictionResult::blocked('country_blocked', 'Not allowed', 'GB', 'mobile');

        $this->assertFalse($result->allowed);
        $this->assertSame('country_blocked', $result->reason);
        $this->assertSame('Not allowed', $result->message);
        $this->assertSame('GB', $result->country);
        $this->assertSame('mobile', $result->numberType);
    }

    public function test_blocked_without_optional_fields(): void
    {
        $result = RestrictionResult::blocked('country_blocked', 'Not allowed');

        $this->assertFalse($result->allowed);
        $this->assertNull($result->country);
        $this->assertNull($result->numberType);
    }
}
