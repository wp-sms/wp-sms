<?php

namespace WSms\Tests\Unit\Mfa\ValueObjects;

use PHPUnit\Framework\TestCase;
use WSms\Mfa\ValueObjects\EnrollmentResult;

class EnrollmentResultTest extends TestCase
{
    public function testSuccessfulResult(): void
    {
        $result = new EnrollmentResult(
            success: true,
            message: 'Enrolled successfully',
            data: ['backup_codes' => ['abc', 'def']],
        );

        $this->assertTrue($result->success);
        $this->assertSame('Enrolled successfully', $result->message);
        $this->assertSame(['backup_codes' => ['abc', 'def']], $result->data);
    }

    public function testFailedResult(): void
    {
        $result = new EnrollmentResult(success: false, message: 'Phone number invalid');

        $this->assertFalse($result->success);
        $this->assertSame('Phone number invalid', $result->message);
        $this->assertSame([], $result->data);
    }

    public function testDefaultValues(): void
    {
        $result = new EnrollmentResult(success: true);

        $this->assertTrue($result->success);
        $this->assertSame('', $result->message);
        $this->assertSame([], $result->data);
    }
}
