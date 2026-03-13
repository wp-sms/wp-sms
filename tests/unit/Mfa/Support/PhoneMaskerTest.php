<?php

namespace WSms\Tests\Unit\Mfa\Support;

use PHPUnit\Framework\TestCase;
use WSms\Mfa\Support\PhoneMasker;

class PhoneMaskerTest extends TestCase
{
    public function testMasksStandardUsPhone(): void
    {
        $this->assertSame('+12*****1234', PhoneMasker::mask('+12025551234'));
    }

    public function testMasksShortPhone(): void
    {
        // 8 chars: keep 3 + 4, mask 1
        $this->assertSame('+44*5678', PhoneMasker::mask('+4415678'));
    }

    public function testReturnsSameForVeryShortPhone(): void
    {
        $this->assertSame('+123', PhoneMasker::mask('+123'));
        $this->assertSame('+1', PhoneMasker::mask('+1'));
    }

    public function testMasksInternationalPhone(): void
    {
        // +449121234567 (13 chars) → +44******4567
        $this->assertSame('+44******4567', PhoneMasker::mask('+449121234567'));
    }

    public function testMasksMinimumMaskableLength(): void
    {
        // 8 chars: +44 + * + 5678
        $this->assertSame('+44*5678', PhoneMasker::mask('+4415678'));
    }

    public function testEmptyStringReturnsEmpty(): void
    {
        $this->assertSame('', PhoneMasker::mask(''));
    }

    public function testExactlySevenChars(): void
    {
        // 7 chars: 3 + 4 = 7, maskLen = 0 → returns unchanged
        $this->assertSame('+441234', PhoneMasker::mask('+441234'));
    }
}
