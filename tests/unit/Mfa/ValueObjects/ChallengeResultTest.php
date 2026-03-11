<?php

namespace WSms\Tests\Unit\Mfa\ValueObjects;

use PHPUnit\Framework\TestCase;
use WSms\Mfa\ValueObjects\ChallengeResult;

class ChallengeResultTest extends TestCase
{
    public function testSuccessfulResult(): void
    {
        $result = new ChallengeResult(
            success: true,
            message: 'Code sent',
            meta: ['masked_phone' => '+1***4567', 'expires_in' => 300],
        );

        $this->assertTrue($result->success);
        $this->assertSame('Code sent', $result->message);
        $this->assertSame('+1***4567', $result->meta['masked_phone']);
        $this->assertSame(300, $result->meta['expires_in']);
    }

    public function testFailedResult(): void
    {
        $result = new ChallengeResult(success: false, message: 'Gateway unavailable');

        $this->assertFalse($result->success);
        $this->assertSame('Gateway unavailable', $result->message);
        $this->assertSame([], $result->meta);
    }

    public function testDefaultValues(): void
    {
        $result = new ChallengeResult(success: true);

        $this->assertTrue($result->success);
        $this->assertSame('', $result->message);
        $this->assertSame([], $result->meta);
    }
}
