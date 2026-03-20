<?php

namespace WSms\Tests\Unit\Messaging\Template;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Template\Renderer\EmailRenderer;
use WSms\Messaging\Template\Renderer\SmsRenderer;
use WSms\Messaging\Template\Renderer\TelegramRenderer;
use WSms\Messaging\Template\Renderer\WhatsAppRenderer;
use WSms\Messaging\Template\ValueObjects\ChannelContent;

class RendererTest extends TestCase
{
    public function testEmailRendererWrapsInHtmlLayout(): void
    {
        $renderer = new EmailRenderer();
        $content = new ChannelContent(
            body: 'Your code is 123456',
            subject: 'Verification',
        );

        $result = $renderer->render($content, ['site_name' => 'Test Site']);

        $this->assertEquals('email', $renderer->getChannel());
        $this->assertStringContainsString('<!DOCTYPE html>', $result->body);
        $this->assertStringContainsString('Your code is 123456', $result->body);
        $this->assertStringContainsString('Test Site', $result->body);
        $this->assertEquals('Verification', $result->subject);
        $this->assertContains('Content-Type: text/html; charset=UTF-8', $result->headers);
    }

    public function testEmailRendererRendersCtaButton(): void
    {
        $renderer = new EmailRenderer();
        $content = new ChannelContent(
            body: 'Click below',
            subject: 'Login',
            cta: 'Log In',
            ctaUrl: 'https://example.com/login',
        );

        $result = $renderer->render($content, ['site_name' => 'Test']);

        $this->assertStringContainsString('https://example.com/login', $result->body);
        $this->assertStringContainsString('Log In', $result->body);
    }

    public function testEmailRendererOmitsCtaWhenEmpty(): void
    {
        $renderer = new EmailRenderer();
        $content = new ChannelContent(body: 'Just text', subject: 'Hi');

        $result = $renderer->render($content, ['site_name' => 'X']);

        $this->assertStringNotContainsString('border-radius:6px;background-color:#2563eb', $result->body);
    }

    public function testSmsRendererStripsHtmlAndCollapsesWhitespace(): void
    {
        $renderer = new SmsRenderer();
        $content = new ChannelContent(
            body: '<b>Your code is: 123</b>  <p>Expires soon.</p>',
        );

        $result = $renderer->render($content, []);

        $this->assertEquals('sms', $renderer->getChannel());
        $this->assertEquals('Your code is: 123 Expires soon.', $result->body);
        $this->assertNull($result->subject);
    }

    public function testTelegramRendererKeepsAllowedTags(): void
    {
        $renderer = new TelegramRenderer();
        $content = new ChannelContent(
            body: '<b>Code: 123</b> <script>alert("x")</script> <a href="url">link</a>',
        );

        $result = $renderer->render($content, []);

        $this->assertEquals('telegram', $renderer->getChannel());
        $this->assertStringContainsString('<b>Code: 123</b>', $result->body);
        $this->assertStringContainsString('<a href="url">link</a>', $result->body);
        $this->assertStringNotContainsString('<script>', $result->body);
        $this->assertEquals('HTML', $result->meta['parse_mode']);
    }

    public function testWhatsAppRendererStripsHtmlAndPassesOtpMeta(): void
    {
        $renderer = new WhatsAppRenderer();
        $content = new ChannelContent(body: '<b>Code: 999</b>');

        $result = $renderer->render($content, ['otp_code' => '999']);

        $this->assertEquals('whatsapp', $renderer->getChannel());
        $this->assertEquals('Code: 999', $result->body);
        $this->assertEquals('999', $result->meta['otp_code']);
    }

    public function testWhatsAppRendererOmitsOtpMetaWhenNotProvided(): void
    {
        $renderer = new WhatsAppRenderer();
        $content = new ChannelContent(body: 'Welcome!');

        $result = $renderer->render($content, []);

        $this->assertArrayNotHasKey('otp_code', $result->meta);
    }
}
