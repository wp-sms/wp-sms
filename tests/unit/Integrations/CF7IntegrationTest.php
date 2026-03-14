<?php

namespace WSms\Tests\Unit\Integrations;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Integrations\ContactForm7\CF7Integration;
use WSms\Verification\VerificationService;

class CF7IntegrationTest extends TestCase
{
    private CF7Integration $integration;
    private MockObject&VerificationService $service;

    protected function setUp(): void
    {
        $this->service = $this->createMock(VerificationService::class);
        $this->integration = new CF7Integration($this->service);
    }

    public function testRenderEmailTagWithPlaceholder(): void
    {
        $tag = new FakeFormTag('your-email', true, ['Enter your email'], ['placeholder']);

        $html = $this->integration->renderEmailTag($tag);

        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('name="your-email"', $html);
        $this->assertStringContainsString('aria-required="true"', $html);
        $this->assertStringContainsString('placeholder="Enter your email"', $html);
        $this->assertStringContainsString('aria-invalid="false"', $html);
        $this->assertStringContainsString('data-wsms-channel="email"', $html);
        $this->assertStringContainsString('wsms_verified_your-email', $html);
        $this->assertStringContainsString('wsms-verify-wrap', $html);
    }

    public function testRenderEmailTagWithoutPlaceholder(): void
    {
        $tag = new FakeFormTag('your-email', false, ['Default value'], []);

        $html = $this->integration->renderEmailTag($tag);

        $this->assertStringNotContainsString('placeholder=', $html);
        $this->assertStringNotContainsString('aria-required', $html);
    }

    public function testRenderPhoneTagProducesCorrectHtml(): void
    {
        $tag = new FakeFormTag('your-phone', false, [], []);

        $html = $this->integration->renderPhoneTag($tag);

        $this->assertStringContainsString('type="tel"', $html);
        $this->assertStringContainsString('name="your-phone"', $html);
        $this->assertStringNotContainsString('aria-required="true"', $html);
        $this->assertStringContainsString('data-wsms-channel="phone"', $html);
    }

    public function testValidateEmailPassesWhenVerified(): void
    {
        $this->service->method('isVerified')->willReturn(true);

        $tag = new FakeFormTag('your-email', true, [], []);
        $result = new FakeValidationResult();

        $_POST['your-email'] = 'test@example.com';
        $_POST['wsms_verified_your-email'] = 'valid-session-token';

        $returned = $this->integration->validateEmail($result, $tag);

        $this->assertFalse($returned->isInvalidated);
    }

    public function testValidateEmailFailsWhenNotVerified(): void
    {
        $this->service->method('isVerified')->willReturn(false);

        $tag = new FakeFormTag('your-email', true, [], []);
        $result = new FakeValidationResult();

        $_POST['your-email'] = 'test@example.com';
        $_POST['wsms_verified_your-email'] = 'invalid-token';

        $returned = $this->integration->validateEmail($result, $tag);

        $this->assertTrue($returned->isInvalidated);
        $this->assertStringContainsString('verify', $returned->invalidMessage);
    }

    public function testValidateEmailFailsWhenRequiredAndEmpty(): void
    {
        $tag = new FakeFormTag('your-email', true, [], []);
        $result = new FakeValidationResult();

        $_POST['your-email'] = '';

        $returned = $this->integration->validateEmail($result, $tag);

        $this->assertTrue($returned->isInvalidated);
        $this->assertStringContainsString('required', $returned->invalidMessage);
    }

    public function testValidateEmailSkipsVerificationWhenOptionalAndEmpty(): void
    {
        $tag = new FakeFormTag('your-email', false, [], []);
        $result = new FakeValidationResult();

        $_POST['your-email'] = '';

        $returned = $this->integration->validateEmail($result, $tag);

        $this->assertFalse($returned->isInvalidated);
    }

    public function testValidateEmailFailsWhenNoSessionToken(): void
    {
        $tag = new FakeFormTag('your-email', true, [], []);
        $result = new FakeValidationResult();

        $_POST['your-email'] = 'test@example.com';
        $_POST['wsms_verified_your-email'] = '';

        $returned = $this->integration->validateEmail($result, $tag);

        $this->assertTrue($returned->isInvalidated);
    }

    public function testValidatePhoneDelegatesToService(): void
    {
        $this->service->expects($this->once())
            ->method('isVerified')
            ->with('phone', $this->anything(), 'valid-token')
            ->willReturn(true);

        $tag = new FakeFormTag('your-phone', true, [], []);
        $result = new FakeValidationResult();

        $_POST['your-phone'] = '+1234567890';
        $_POST['wsms_verified_your-phone'] = 'valid-token';

        $returned = $this->integration->validatePhone($result, $tag);

        $this->assertFalse($returned->isInvalidated);
    }

    public function testRegisterMessagesAddsVerificationMessages(): void
    {
        $messages = $this->integration->registerMessages([]);

        $this->assertArrayHasKey('wsms_verify_required', $messages);
        $this->assertArrayHasKey('wsms_verify_not_verified', $messages);
        $this->assertArrayHasKey('description', $messages['wsms_verify_required']);
        $this->assertArrayHasKey('default', $messages['wsms_verify_required']);
        $this->assertArrayHasKey('description', $messages['wsms_verify_not_verified']);
        $this->assertArrayHasKey('default', $messages['wsms_verify_not_verified']);
    }

    public function testRegisterMessagesPreservesExistingMessages(): void
    {
        $existing = ['some_message' => ['description' => 'test', 'default' => 'Test']];
        $messages = $this->integration->registerMessages($existing);

        $this->assertArrayHasKey('some_message', $messages);
        $this->assertArrayHasKey('wsms_verify_required', $messages);
    }

    protected function tearDown(): void
    {
        unset($_POST['your-email'], $_POST['wsms_verified_your-email']);
        unset($_POST['your-phone'], $_POST['wsms_verified_your-phone']);
    }
}

/**
 * Minimal CF7 form tag stub matching WPCF7_FormTag API.
 */
class FakeFormTag
{
    public string $name;
    public array $values;

    private bool $required;
    private array $options;

    public function __construct(string $name, bool $required, array $values = [], array $options = [])
    {
        $this->name = $name;
        $this->required = $required;
        $this->values = $values;
        $this->options = $options;
    }

    public function is_required(): bool
    {
        return $this->required;
    }

    public function has_option(string $name): bool
    {
        return in_array($name, $this->options, true);
    }

    public function get_option(string $name, $default = '', $single = false)
    {
        return $single ? '' : [];
    }
}

/**
 * Minimal CF7 validation result stub for testing.
 */
class FakeValidationResult
{
    public bool $isInvalidated = false;
    public string $invalidMessage = '';

    public function invalidate($tag, string $message): void
    {
        $this->isInvalidated = true;
        $this->invalidMessage = $message;
    }
}
