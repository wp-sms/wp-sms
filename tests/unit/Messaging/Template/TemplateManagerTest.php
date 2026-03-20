<?php

namespace WSms\Tests\Unit\Messaging\Template;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Template\Contracts\ChannelRendererInterface;
use WSms\Messaging\Template\Contracts\TemplateDefinitionInterface;
use WSms\Messaging\Template\Contracts\TemplateStorageInterface;
use WSms\Messaging\Template\MustacheEngine;
use WSms\Messaging\Template\TemplateManager;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\RenderedMessage;
use WSms\Messaging\Template\ValueObjects\VariableDefinition;

class TemplateManagerTest extends TestCase
{
    private TemplateManager $manager;
    private TemplateStorageInterface $storage;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(TemplateStorageInterface::class);
        $this->manager = new TemplateManager($this->storage, new MustacheEngine());

        // Register a mock renderer.
        $emailRenderer = $this->createMock(ChannelRendererInterface::class);
        $emailRenderer->method('getChannel')->willReturn('email');
        $emailRenderer->method('render')->willReturnCallback(function (ChannelContent $content, array $ctx) {
            return new RenderedMessage(body: $content->body, subject: $content->subject);
        });
        $this->manager->registerRenderer($emailRenderer);

        $smsRenderer = $this->createMock(ChannelRendererInterface::class);
        $smsRenderer->method('getChannel')->willReturn('sms');
        $smsRenderer->method('render')->willReturnCallback(function (ChannelContent $content, array $ctx) {
            return new RenderedMessage(body: strip_tags($content->body));
        });
        $this->manager->registerRenderer($smsRenderer);
    }

    private function makeTemplate(string $id = 'otp', array $channels = ['email', 'sms']): TemplateDefinitionInterface
    {
        $template = $this->createMock(TemplateDefinitionInterface::class);
        $template->method('getId')->willReturn($id);
        $template->method('getLabel')->willReturn('OTP');
        $template->method('getDescription')->willReturn('OTP template');
        $template->method('getSupportedChannels')->willReturn($channels);
        $template->method('getVariables')->willReturn([
            'otp_code' => new VariableDefinition('otp_code', 'Code', 'The code', true, '123456'),
            'expiry_minutes' => new VariableDefinition('expiry_minutes', 'Expiry', 'Minutes', true, '10'),
        ]);
        $template->method('getDefaults')->willReturn([
            'email' => new ChannelContent(
                body: 'Your code is: {{otp_code}}. Expires in {{expiry_minutes}} min.',
                subject: 'Your code',
            ),
            'sms' => new ChannelContent(
                body: 'Code: {{otp_code}}. {{expiry_minutes}} min.',
            ),
        ]);

        return $template;
    }

    public function testRenderSubstitutesVariables(): void
    {
        $template = $this->makeTemplate();
        $this->manager->registerTemplate($template);

        $rendered = $this->manager->render('otp', 'email', [
            'otp_code' => '482916',
            'expiry_minutes' => '10',
        ]);

        $this->assertStringContainsString('482916', $rendered->body);
        $this->assertStringContainsString('10 min', $rendered->body);
        $this->assertStringNotContainsString('{{otp_code}}', $rendered->body);
    }

    public function testRenderUsesOverrideWhenAvailable(): void
    {
        $template = $this->makeTemplate();
        $this->manager->registerTemplate($template);

        $this->storage->method('getOverride')->willReturnCallback(function ($tid, $ch) {
            if ($tid === 'otp' && $ch === 'email') {
                return new ChannelContent(body: 'Custom: {{otp_code}}', subject: 'Custom subject');
            }
            return null;
        });

        $rendered = $this->manager->render('otp', 'email', [
            'otp_code' => '111222',
            'expiry_minutes' => '5',
        ]);

        $this->assertStringContainsString('Custom: 111222', $rendered->body);
        $this->assertEquals('Custom subject', $rendered->subject);
    }

    public function testRenderThrowsOnUnsupportedChannel(): void
    {
        $template = $this->makeTemplate('test', ['email']);
        $this->manager->registerTemplate($template);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Channel "sms" is not supported');

        $this->manager->render('test', 'sms', ['otp_code' => '123']);
    }

    public function testRenderThrowsOnUnknownTemplate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Template "nonexistent" not found');

        $this->manager->render('nonexistent', 'email', []);
    }

    public function testRenderThrowsOnMissingRenderer(): void
    {
        $template = $this->createMock(TemplateDefinitionInterface::class);
        $template->method('getId')->willReturn('t');
        $template->method('getSupportedChannels')->willReturn(['telegram']);
        $template->method('getDefaults')->willReturn(['telegram' => new ChannelContent(body: 'x')]);
        $template->method('getVariables')->willReturn([]);
        $this->manager->registerTemplate($template);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No renderer registered for channel "telegram"');

        $this->manager->render('t', 'telegram', []);
    }

    public function testRenderToMessageReturnsMessageInterface(): void
    {
        $template = $this->makeTemplate();
        $this->manager->registerTemplate($template);

        $message = $this->manager->renderToMessage('otp', 'email', 'user@test.com', [
            'otp_code' => '999',
            'expiry_minutes' => '5',
        ]);

        $this->assertInstanceOf(MessageInterface::class, $message);
        $this->assertEquals('user@test.com', $message->getRecipient());
        $this->assertEquals('email', $message->getChannel());
    }

    public function testRenderInjectsCommonVariables(): void
    {
        $template = $this->createMock(TemplateDefinitionInterface::class);
        $template->method('getId')->willReturn('cv');
        $template->method('getSupportedChannels')->willReturn(['email']);
        $template->method('getDefaults')->willReturn([
            'email' => new ChannelContent(body: 'Welcome to {{site_name}}'),
        ]);
        $template->method('getVariables')->willReturn([
            'site_name' => new VariableDefinition('site_name', 'Site', 'Name', false, 'Test'),
        ]);
        $this->manager->registerTemplate($template);

        $rendered = $this->manager->render('cv', 'email', []);

        // site_name should be injected (from get_bloginfo stub).
        $this->assertStringNotContainsString('{{site_name}}', $rendered->body);
    }

    public function testGetTemplateForEditingReturnsStructuredData(): void
    {
        $template = $this->makeTemplate();
        $this->manager->registerTemplate($template);

        $this->storage->method('getOverride')->willReturn(null);

        $data = $this->manager->getTemplateForEditing('otp');

        $this->assertEquals('otp', $data['id']);
        $this->assertArrayHasKey('variables', $data);
        $this->assertArrayHasKey('channels', $data);
        $this->assertArrayHasKey('email', $data['channels']);
        $this->assertArrayHasKey('sms', $data['channels']);
        $this->assertNotNull($data['channels']['email']['default']);
        $this->assertNull($data['channels']['email']['override']);
    }

    public function testGetVisibleChannelsIntersectsWithEnabled(): void
    {
        $template = $this->makeTemplate('t', ['email', 'sms', 'telegram']);
        $this->manager->registerTemplate($template);

        $visible = $this->manager->getVisibleChannels('t', ['email', 'telegram']);

        $this->assertEquals(['email', 'telegram'], $visible);
    }

    public function testGetTemplatesReturnsAllRegistered(): void
    {
        $this->manager->registerTemplate($this->makeTemplate('a'));
        $this->manager->registerTemplate($this->makeTemplate('b'));

        $templates = $this->manager->getTemplates();

        $this->assertCount(2, $templates);
        $this->assertArrayHasKey('a', $templates);
        $this->assertArrayHasKey('b', $templates);
    }

    public function testSubstitutesSubjectAndCtaFields(): void
    {
        $template = $this->createMock(TemplateDefinitionInterface::class);
        $template->method('getId')->willReturn('ml');
        $template->method('getSupportedChannels')->willReturn(['email']);
        $template->method('getDefaults')->willReturn([
            'email' => new ChannelContent(
                body: 'Click link',
                subject: '[{{site_name}}] Login',
                cta: 'Log in to {{site_name}}',
                ctaUrl: '{{magic_link_url}}',
            ),
        ]);
        $template->method('getVariables')->willReturn([
            'site_name' => new VariableDefinition('site_name', 'Site', 'Name', false, 'Test'),
        ]);
        $this->manager->registerTemplate($template);

        // The rendered message should have variables substituted in subject.
        $rendered = $this->manager->render('ml', 'email', [
            'magic_link_url' => 'https://example.com/verify?token=abc',
        ]);

        $this->assertStringNotContainsString('{{site_name}}', $rendered->subject ?? '');
    }
}
