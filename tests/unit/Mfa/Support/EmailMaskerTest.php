<?php

namespace WSms\Tests\Unit\Mfa\Support;

use PHPUnit\Framework\TestCase;
use WSms\Mfa\Support\EmailMasker;

class EmailMaskerTest extends TestCase
{
    public function testMasksStandardEmail(): void
    {
        $this->assertSame('n***@gmail.com', EmailMasker::mask('navid@gmail.com'));
    }

    public function testMasksLongLocal(): void
    {
        $this->assertSame('j***@example.com', EmailMasker::mask('johndoe@example.com'));
    }

    public function testMasksSingleCharLocal(): void
    {
        $this->assertSame('a***@test.com', EmailMasker::mask('a@test.com'));
    }

    public function testMasksTwoCharLocal(): void
    {
        $this->assertSame('a*@test.com', EmailMasker::mask('ab@test.com'));
    }

    public function testHandlesNoAtSign(): void
    {
        $this->assertSame('invalid-email', EmailMasker::mask('invalid-email'));
    }

    public function testHandlesEmptyString(): void
    {
        $this->assertSame('', EmailMasker::mask(''));
    }

    public function testPreservesDomain(): void
    {
        $result = EmailMasker::mask('user@my-domain.co.uk');

        $this->assertStringEndsWith('@my-domain.co.uk', $result);
    }

    public function testMasksThreeCharLocal(): void
    {
        $this->assertSame('a**@test.com', EmailMasker::mask('abc@test.com'));
    }

    public function testMasksFourCharLocal(): void
    {
        $this->assertSame('a***@test.com', EmailMasker::mask('abcd@test.com'));
    }
}
