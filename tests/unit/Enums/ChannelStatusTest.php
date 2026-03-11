<?php

namespace WSms\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use WSms\Enums\ChannelStatus;

class ChannelStatusTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('pending', ChannelStatus::Pending->value);
        $this->assertSame('active', ChannelStatus::Active->value);
        $this->assertSame('disabled', ChannelStatus::Disabled->value);
    }

    public function testFromString(): void
    {
        $this->assertSame(ChannelStatus::Active, ChannelStatus::from('active'));
        $this->assertSame(ChannelStatus::Pending, ChannelStatus::from('pending'));
        $this->assertSame(ChannelStatus::Disabled, ChannelStatus::from('disabled'));
    }

    public function testTryFromInvalidReturnsNull(): void
    {
        $this->assertNull(ChannelStatus::tryFrom('invalid'));
    }

    public function testAllCasesCount(): void
    {
        $this->assertCount(3, ChannelStatus::cases());
    }
}
