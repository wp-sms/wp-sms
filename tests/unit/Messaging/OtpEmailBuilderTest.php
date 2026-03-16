<?php

namespace WSms\Tests\Unit\Messaging;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Message\EmailMessage;
use WSms\Messaging\OtpEmailBuilder;

class OtpEmailBuilderTest extends TestCase
{
    public function testBuildReturnsEmailMessage(): void
    {
        $email = OtpEmailBuilder::build('user@example.com', '123456', 300);

        $this->assertInstanceOf(EmailMessage::class, $email);
        $this->assertSame('email', $email->getChannel());
        $this->assertSame('user@example.com', $email->getRecipient());
    }

    public function testSubjectContainsSiteName(): void
    {
        $email = OtpEmailBuilder::build('user@example.com', '123456', 300);

        $meta = $email->getMeta();
        $this->assertStringContainsString('Test Site', $meta['subject']);
        $this->assertStringContainsString('verification code', $meta['subject']);
    }

    public function testBodyContainsEscapedOtpCode(): void
    {
        $email = OtpEmailBuilder::build('user@example.com', '789012', 300);

        $this->assertStringContainsString('789012', $email->getBody());
    }

    public function testBodyContainsExpiryInMinutes(): void
    {
        // 300 seconds = 5 minutes
        $email = OtpEmailBuilder::build('user@example.com', '123456', 300);
        $this->assertStringContainsString('5', $email->getBody());

        // 90 seconds = ceil(1.5) = 2 minutes
        $email2 = OtpEmailBuilder::build('user@example.com', '123456', 90);
        $this->assertStringContainsString('2', $email2->getBody());
    }

    public function testBodyContainsHtmlContentTypeHeader(): void
    {
        $email = OtpEmailBuilder::build('user@example.com', '123456', 300);

        $meta = $email->getMeta();
        $this->assertContains('Content-Type: text/html; charset=UTF-8', $meta['headers']);
    }
}
