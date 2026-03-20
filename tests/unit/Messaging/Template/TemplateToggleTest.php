<?php

namespace WSms\Tests\Unit\Messaging\Template;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Enums\TemplateType;
use WSms\Messaging\Template\Contracts\TemplateDefinitionInterface;
use WSms\Messaging\Template\Contracts\TemplateStorageInterface;
use WSms\Messaging\Template\Contracts\ToggleableTemplateInterface;
use WSms\Messaging\Template\Definitions\AccountAlertTemplate;
use WSms\Messaging\Template\Definitions\AccountSuspendedTemplate;
use WSms\Messaging\Template\Definitions\WelcomeTemplate;
use WSms\Messaging\Template\MustacheEngine;
use WSms\Messaging\Template\Storage\OptionStorage;
use WSms\Messaging\Template\TemplateManager;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\VariableDefinition;

class TemplateToggleTest extends TestCase
{
    private TemplateManager $manager;
    private MockObject&TemplateStorageInterface $storage;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(TemplateStorageInterface::class);
        $this->manager = new TemplateManager($this->storage, new MustacheEngine());
    }

    private function makeNonToggleableTemplate(string $id = 'otp'): TemplateDefinitionInterface
    {
        $template = $this->createMock(TemplateDefinitionInterface::class);
        $template->method('getId')->willReturn($id);
        $template->method('getLabel')->willReturn('OTP');
        $template->method('getDescription')->willReturn('OTP template');
        $template->method('getSupportedChannels')->willReturn(['email']);
        $template->method('getVariables')->willReturn([]);
        $template->method('getDefaults')->willReturn([
            'email' => new ChannelContent(body: 'Code: 123'),
        ]);

        return $template;
    }

    private function makeToggleableTemplate(string $id = 'welcome', bool $enabledByDefault = false): ToggleableTemplateInterface
    {
        $template = $this->createMock(ToggleableTemplateInterface::class);
        $template->method('getId')->willReturn($id);
        $template->method('getLabel')->willReturn('Welcome');
        $template->method('getDescription')->willReturn('Welcome template');
        $template->method('getSupportedChannels')->willReturn(['email']);
        $template->method('getVariables')->willReturn([]);
        $template->method('getDefaults')->willReturn([
            'email' => new ChannelContent(body: 'Welcome!'),
        ]);
        $template->method('isEnabledByDefault')->willReturn($enabledByDefault);

        return $template;
    }

    // --- isToggleable ---

    public function testNonToggleableTemplateIsNotToggleable(): void
    {
        $this->manager->registerTemplate($this->makeNonToggleableTemplate());
        $this->assertFalse($this->manager->isToggleable('otp'));
    }

    public function testToggleableTemplateIsToggleable(): void
    {
        $this->manager->registerTemplate($this->makeToggleableTemplate());
        $this->assertTrue($this->manager->isToggleable('welcome'));
    }

    // --- isEnabled: non-toggleable ---

    public function testNonToggleableTemplateIsAlwaysEnabled(): void
    {
        $this->storage->method('isEnabled')->willReturn(null);

        $this->manager->registerTemplate($this->makeNonToggleableTemplate());
        $this->assertTrue($this->manager->isEnabled('otp'));
    }

    // --- isEnabled: toggleable with no stored state ---

    public function testToggleableWithNoStoredStateReturnsDefault(): void
    {
        $this->storage->method('isEnabled')->willReturn(null);

        $this->manager->registerTemplate($this->makeToggleableTemplate('welcome', false));
        $this->assertFalse($this->manager->isEnabled('welcome'));
    }

    public function testToggleableEnabledByDefaultWithNoStoredState(): void
    {
        $this->storage->method('isEnabled')->willReturn(null);

        $this->manager->registerTemplate($this->makeToggleableTemplate('test_template', true));
        $this->assertTrue($this->manager->isEnabled('test_template'));
    }

    // --- isEnabled: toggleable with stored state ---

    public function testToggleableWithStoredTrue(): void
    {
        $this->storage->method('isEnabled')->willReturn(true);

        $this->manager->registerTemplate($this->makeToggleableTemplate('welcome', false));
        $this->assertTrue($this->manager->isEnabled('welcome'));
    }

    public function testToggleableWithStoredFalse(): void
    {
        $this->storage->method('isEnabled')->willReturn(false);

        $this->manager->registerTemplate($this->makeToggleableTemplate('welcome', true));
        $this->assertFalse($this->manager->isEnabled('welcome'));
    }

    // --- getTemplateForEditing includes toggle fields ---

    public function testGetTemplateForEditingIncludesToggleFields(): void
    {
        $this->storage->method('isEnabled')->willReturn(null);
        $this->storage->method('getOverride')->willReturn(null);

        $this->manager->registerTemplate($this->makeToggleableTemplate('welcome', false));
        $data = $this->manager->getTemplateForEditing('welcome');

        $this->assertArrayHasKey('toggleable', $data);
        $this->assertArrayHasKey('enabled', $data);
        $this->assertTrue($data['toggleable']);
        $this->assertFalse($data['enabled']);
    }

    public function testGetTemplateForEditingNonToggleable(): void
    {
        $this->storage->method('isEnabled')->willReturn(null);
        $this->storage->method('getOverride')->willReturn(null);

        $this->manager->registerTemplate($this->makeNonToggleableTemplate());
        $data = $this->manager->getTemplateForEditing('otp');

        $this->assertFalse($data['toggleable']);
        $this->assertTrue($data['enabled']);
    }

    // --- OptionStorage: isEnabled / setEnabled ---

    public function testOptionStorageIsEnabledReturnsNullForUnknown(): void
    {
        $GLOBALS['_test_options'] = [];
        $storage = new OptionStorage();

        $this->assertNull($storage->isEnabled('nonexistent'));
        unset($GLOBALS['_test_options']);
    }

    public function testOptionStorageSetAndGetEnabled(): void
    {
        $GLOBALS['_test_options'] = [];
        $storage = new OptionStorage();

        $storage->setEnabled('welcome', true);
        $this->assertTrue($storage->isEnabled('welcome'));

        $storage->setEnabled('welcome', false);
        $this->assertFalse($storage->isEnabled('welcome'));

        unset($GLOBALS['_test_options']);
    }

    public function testOptionStorageEnabledDoesNotAffectOverrides(): void
    {
        $GLOBALS['_test_options'] = [];
        $storage = new OptionStorage();

        $storage->saveOverride('welcome', 'email', new ChannelContent(body: 'Custom welcome'));
        $storage->setEnabled('welcome', true);

        $override = $storage->getOverride('welcome', 'email');
        $this->assertNotNull($override);
        $this->assertEquals('Custom welcome', $override->body);
        $this->assertTrue($storage->isEnabled('welcome'));

        unset($GLOBALS['_test_options']);
    }

    public function testOptionStorageGetAllOverridesExcludesEnabledKey(): void
    {
        $GLOBALS['_test_options'] = [];
        $storage = new OptionStorage();

        $storage->saveOverride('welcome', 'email', new ChannelContent(body: 'Custom'));
        $storage->setEnabled('welcome', true);

        $all = $storage->getAllOverrides();
        $this->assertArrayHasKey('welcome', $all);
        $this->assertArrayHasKey('email', $all['welcome']);
        $this->assertArrayNotHasKey('_enabled', $all['welcome']);

        unset($GLOBALS['_test_options']);
    }

    // --- Real template definitions ---

    public function testAccountSuspendedTemplateDefinition(): void
    {
        $template = new AccountSuspendedTemplate();

        $this->assertEquals('account_suspended', $template->getId());
        $this->assertInstanceOf(ToggleableTemplateInterface::class, $template);
        $this->assertFalse($template->isEnabledByDefault());
        $this->assertEquals(['email'], $template->getSupportedChannels());

        $vars = $template->getVariables();
        $this->assertArrayHasKey('user_name', $vars);
        $this->assertArrayHasKey('site_name', $vars);

        $defaults = $template->getDefaults();
        $this->assertArrayHasKey('email', $defaults);
        $this->assertNotEmpty($defaults['email']->body);
        $this->assertNotEmpty($defaults['email']->subject);
    }

    public function testWelcomeTemplateIsToggleable(): void
    {
        $template = new WelcomeTemplate();

        $this->assertInstanceOf(ToggleableTemplateInterface::class, $template);
        $this->assertFalse($template->isEnabledByDefault());
    }

    public function testAccountAlertTemplateIsToggleable(): void
    {
        $template = new AccountAlertTemplate();

        $this->assertInstanceOf(ToggleableTemplateInterface::class, $template);
        $this->assertFalse($template->isEnabledByDefault());
    }

    public function testAccountSuspendedEnumCaseExists(): void
    {
        $this->assertEquals('account_suspended', TemplateType::AccountSuspended->value);
    }
}
