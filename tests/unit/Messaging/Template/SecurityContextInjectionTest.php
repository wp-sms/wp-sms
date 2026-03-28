<?php

namespace WSms\Tests\Unit\Messaging\Template;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Template\Contracts\ChannelRendererInterface;
use WSms\Messaging\Template\Contracts\TemplateDefinitionInterface;
use WSms\Messaging\Template\Contracts\TemplateStorageInterface;
use WSms\Messaging\Template\MustacheEngine;
use WSms\Messaging\Template\TemplateManager;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\RenderedMessage;
use WSms\Messaging\Template\ValueObjects\VariableDefinition;

class SecurityContextInjectionTest extends TestCase
{
    private TemplateManager $manager;

    protected function setUp(): void
    {
        $storage = $this->createMock(TemplateStorageInterface::class);
        $this->manager = new TemplateManager($storage, new MustacheEngine());

        $renderer = $this->createMock(ChannelRendererInterface::class);
        $renderer->method('getChannel')->willReturn('email');
        $renderer->method('render')->willReturnCallback(
            fn (ChannelContent $content, array $ctx) => new RenderedMessage(body: $content->body)
        );
        $this->manager->registerRenderer($renderer);

        // Clean superglobals
        unset(
            $_SERVER['HTTP_USER_AGENT'],
            $_SERVER['HTTP_CF_IPCOUNTRY'],
            $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'],
            $_SERVER['HTTP_X_COUNTRY_CODE'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['REMOTE_ADDR'],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $_SERVER['HTTP_USER_AGENT'],
            $_SERVER['HTTP_CF_IPCOUNTRY'],
            $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'],
            $_SERVER['HTTP_X_COUNTRY_CODE'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['REMOTE_ADDR'],
        );
    }

    private function registerTemplateWithSecurityContext(): void
    {
        $template = $this->createMock(TemplateDefinitionInterface::class);
        $template->method('getId')->willReturn('test');
        $template->method('getSupportedChannels')->willReturn(['email']);
        $template->method('getVariables')->willReturn([
            'security_context' => new VariableDefinition('security_context', 'Security Context', 'Details', false, ''),
        ]);
        $template->method('getDefaults')->willReturn([
            'email' => new ChannelContent(body: 'Info: {{security_context}}'),
        ]);

        $this->manager->registerTemplate($template);
    }

    public function test_includes_device_location_and_ip_when_all_available(): void
    {
        $this->registerTemplateWithSecurityContext();
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        $rendered = $this->manager->render('test', 'email', []);

        $this->assertStringContainsString('Device: Chrome on Windows', $rendered->body);
        $this->assertStringContainsString('Location: US', $rendered->body);
        $this->assertStringContainsString('IP: 203.0.113.1', $rendered->body);
        $this->assertStringContainsString('If you did not request this', $rendered->body);
    }

    public function test_skips_device_line_when_ua_unrecognizable(): void
    {
        $this->registerTemplateWithSecurityContext();
        $_SERVER['HTTP_USER_AGENT'] = 'curl/7.68.0';
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'DE';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.2';

        $rendered = $this->manager->render('test', 'email', []);

        $this->assertStringNotContainsString('Device:', $rendered->body);
        $this->assertStringContainsString('Location: DE', $rendered->body);
        $this->assertStringContainsString('IP: 203.0.113.2', $rendered->body);
    }

    public function test_skips_location_line_when_no_geo_header(): void
    {
        $this->registerTemplateWithSecurityContext();
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2.1 Safari/605.1.15';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.3';

        $rendered = $this->manager->render('test', 'email', []);

        $this->assertStringContainsString('Device: Safari on macOS', $rendered->body);
        $this->assertStringNotContainsString('Location:', $rendered->body);
        $this->assertStringContainsString('IP: 203.0.113.3', $rendered->body);
    }

    public function test_skips_ip_line_when_no_ip_available(): void
    {
        $this->registerTemplateWithSecurityContext();
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0';
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'JP';

        $rendered = $this->manager->render('test', 'email', []);

        $this->assertStringContainsString('Device: Firefox on Linux', $rendered->body);
        $this->assertStringContainsString('Location: JP', $rendered->body);
        $this->assertStringNotContainsString('IP:', $rendered->body);
    }

    public function test_empty_when_nothing_available(): void
    {
        $this->registerTemplateWithSecurityContext();

        $rendered = $this->manager->render('test', 'email', []);

        // security_context should be empty, leaving just the surrounding text
        $this->assertSame('Info: ', $rendered->body);
        $this->assertStringNotContainsString('Device:', $rendered->body);
        $this->assertStringNotContainsString('Location:', $rendered->body);
        $this->assertStringNotContainsString('IP:', $rendered->body);
        $this->assertStringNotContainsString('If you did not request this', $rendered->body);
    }

    public function test_warning_line_appended_when_any_data_present(): void
    {
        $this->registerTemplateWithSecurityContext();
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $rendered = $this->manager->render('test', 'email', []);

        // Only IP is available — warning should still be appended
        $this->assertStringContainsString('IP: 10.0.0.1', $rendered->body);
        $this->assertStringContainsString('If you did not request this, please secure your account immediately.', $rendered->body);
    }

    public function test_does_not_override_explicitly_provided_security_context(): void
    {
        $this->registerTemplateWithSecurityContext();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        $rendered = $this->manager->render('test', 'email', [
            'security_context' => 'Custom context',
        ]);

        $this->assertSame('Info: Custom context', $rendered->body);
    }

    public function test_not_injected_when_template_does_not_declare_it(): void
    {
        $template = $this->createMock(TemplateDefinitionInterface::class);
        $template->method('getId')->willReturn('plain');
        $template->method('getSupportedChannels')->willReturn(['email']);
        $template->method('getVariables')->willReturn([]);
        $template->method('getDefaults')->willReturn([
            'email' => new ChannelContent(body: 'Hello'),
        ]);
        $this->manager->registerTemplate($template);

        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        $rendered = $this->manager->render('plain', 'email', []);

        $this->assertSame('Hello', $rendered->body);
    }
}
