<?php

namespace WSms\Tests\Unit\Messaging\Template;

use PHPUnit\Framework\TestCase;
use WSms\Enums\TemplateType;
use WSms\Messaging\Template\Definitions\AccountAlertTemplate;
use WSms\Messaging\Template\Definitions\EmailVerificationTemplate;
use WSms\Messaging\Template\Definitions\MagicLinkTemplate;
use WSms\Messaging\Template\Definitions\OtpTemplate;
use WSms\Messaging\Template\Definitions\OtpWithMagicLinkTemplate;
use WSms\Messaging\Template\Definitions\PasswordResetTemplate;
use WSms\Messaging\Template\Definitions\PhoneVerificationTemplate;
use WSms\Messaging\Template\Definitions\WelcomeTemplate;
use WSms\Messaging\Template\Contracts\TemplateDefinitionInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\VariableDefinition;

class DefinitionsTest extends TestCase
{
    /**
     * @dataProvider templateProvider
     */
    public function testTemplateHasValidStructure(TemplateDefinitionInterface $template, TemplateType $expectedType): void
    {
        $this->assertEquals($expectedType->value, $template->getId());
        $this->assertNotEmpty($template->getLabel());
        $this->assertNotEmpty($template->getDescription());
        $this->assertNotEmpty($template->getSupportedChannels());

        $defaults = $template->getDefaults();
        $this->assertNotEmpty($defaults);

        // Every supported channel must have a default.
        foreach ($template->getSupportedChannels() as $channel) {
            $this->assertArrayHasKey($channel, $defaults, "Missing default for channel: {$channel}");
            $this->assertInstanceOf(ChannelContent::class, $defaults[$channel]);
            $this->assertNotEmpty($defaults[$channel]->body, "Empty body for channel: {$channel}");
        }

        // Defaults should not contain unsupported channels.
        foreach (array_keys($defaults) as $channel) {
            $this->assertContains($channel, $template->getSupportedChannels(), "Default for unsupported channel: {$channel}");
        }
    }

    /**
     * @dataProvider templateProvider
     */
    public function testTemplateVariablesAreValid(TemplateDefinitionInterface $template): void
    {
        $variables = $template->getVariables();

        foreach ($variables as $name => $def) {
            $this->assertInstanceOf(VariableDefinition::class, $def);
            $this->assertEquals($name, $def->name);
            $this->assertNotEmpty($def->label);
            $this->assertNotEmpty($def->example);
        }
    }

    /**
     * @dataProvider templateProvider
     */
    public function testTemplateDefaultsContainRequiredVariablePlaceholders(TemplateDefinitionInterface $template): void
    {
        $required = array_filter($template->getVariables(), fn ($v) => $v->required);
        $defaults = $template->getDefaults();

        if (empty($required)) {
            $this->assertNotEmpty($defaults, 'Template should still have defaults even without required variables');
            return;
        }

        foreach ($defaults as $channel => $content) {
            $allText = $content->body . ($content->subject ?? '') . ($content->cta ?? '') . ($content->ctaUrl ?? '');

            foreach ($required as $name => $def) {
                $this->assertStringContainsString(
                    "{{$name}}",
                    $allText,
                    "Required variable '{$name}' not found in {$channel} default for template '{$template->getId()}'",
                );
            }
        }
    }

    public static function templateProvider(): array
    {
        return [
            'otp' => [new OtpTemplate(), TemplateType::Otp],
            'magic_link' => [new MagicLinkTemplate(), TemplateType::MagicLink],
            'otp_with_magic_link' => [new OtpWithMagicLinkTemplate(), TemplateType::OtpWithMagicLink],
            'email_verification' => [new EmailVerificationTemplate(), TemplateType::EmailVerification],
            'phone_verification' => [new PhoneVerificationTemplate(), TemplateType::PhoneVerification],
            'password_reset' => [new PasswordResetTemplate(), TemplateType::PasswordReset],
            'welcome' => [new WelcomeTemplate(), TemplateType::Welcome],
            'account_alert' => [new AccountAlertTemplate(), TemplateType::AccountAlert],
        ];
    }

    public function testOtpSupportsFiveChannels(): void
    {
        $t = new OtpTemplate();
        $this->assertEquals(['email', 'sms', 'whatsapp', 'telegram', 'rcs'], $t->getSupportedChannels());
    }

    public function testEmailVerificationIsEmailOnly(): void
    {
        $t = new EmailVerificationTemplate();
        $this->assertEquals(['email'], $t->getSupportedChannels());
    }

    public function testPasswordResetIsEmailOnly(): void
    {
        $t = new PasswordResetTemplate();
        $this->assertEquals(['email'], $t->getSupportedChannels());
    }

    public function testPhoneVerificationExcludesEmail(): void
    {
        $t = new PhoneVerificationTemplate();
        $this->assertNotContains('email', $t->getSupportedChannels());
    }

    public function testEmailDefaultsHaveSubject(): void
    {
        $templates = [
            new OtpTemplate(),
            new MagicLinkTemplate(),
            new EmailVerificationTemplate(),
            new PasswordResetTemplate(),
            new WelcomeTemplate(),
            new AccountAlertTemplate(),
        ];

        foreach ($templates as $t) {
            $defaults = $t->getDefaults();
            if (isset($defaults['email'])) {
                $this->assertNotNull($defaults['email']->subject, "Email default for {$t->getId()} missing subject");
            }
        }
    }

    public function testSmsDefaultsHaveNoSubject(): void
    {
        $templates = [
            new OtpTemplate(),
            new PhoneVerificationTemplate(),
        ];

        foreach ($templates as $t) {
            $defaults = $t->getDefaults();
            if (isset($defaults['sms'])) {
                $this->assertNull($defaults['sms']->subject, "SMS default for {$t->getId()} should not have subject");
            }
        }
    }
}
