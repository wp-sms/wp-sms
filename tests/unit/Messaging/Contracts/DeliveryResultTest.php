<?php

namespace WSms\Tests\Unit\Messaging\Contracts;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Contracts\DeliveryResult;

class DeliveryResultTest extends TestCase
{
    public function testSentIsNotRetryable(): void
    {
        $result = DeliveryResult::sent('pid-123');

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertFalse($result->retryable);
    }

    public function testQueuedIsNotRetryable(): void
    {
        $result = DeliveryResult::queued('pid-456');

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertFalse($result->retryable);
    }

    public function testFailedDefaultsToNotRetryable(): void
    {
        $result = DeliveryResult::failed('Some error');

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertFalse($result->retryable);
        $this->assertSame('Some error', $result->error);
    }

    public function testFailedCanBeRetryable(): void
    {
        $result = DeliveryResult::failed('Timeout', [], true);

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertTrue($result->retryable);
        $this->assertSame('Timeout', $result->error);
    }

    public function testFailedRetryableWithMeta(): void
    {
        $result = DeliveryResult::failed('HTTP 503', ['http_status' => 503], true);

        $this->assertTrue($result->retryable);
        $this->assertSame(['http_status' => 503], $result->meta);
    }

}
