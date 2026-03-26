<?php

namespace WSms\Tests\Unit\Verification\Plugin;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Branding\BrandingRepository;
use WSms\PhoneRestriction\RestrictionSettings;
use WSms\Verification\Plugin\ContactForm7\CF7Integration;
use WSms\Verification\VerificationService;

class CF7IntegrationTest extends TestCase
{
    private CF7Integration $integration;
    private MockObject&VerificationService $service;
    private MockObject&RestrictionSettings $restrictionSettings;

    protected function setUp(): void
    {
        $this->service = $this->createMock(VerificationService::class);
        $this->restrictionSettings = $this->createMock(RestrictionSettings::class);
        $this->integration = new CF7Integration($this->service, $this->restrictionSettings, new BrandingRepository());
    }

    // ── Email rendering tests (unchanged) ──────────────────────────

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

    // ── Phone rendering tests ──────────────────────────────────────

    public function testPhoneRendersContainer(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], []);

        $html = $this->integration->renderPhoneTag($tag);

        $this->assertStringContainsString('wsms-phone-container', $html);
        $this->assertStringContainsString('data-wsms-field="my-phone"', $html);
        $this->assertStringContainsString('wsms-phone-wrap', $html);
        // No verify elements
        $this->assertStringNotContainsString('wsms-verify-widget-container', $html);
        $this->assertStringNotContainsString('wsms-verified-flag', $html);
    }

    public function testPhoneWithVerifyHasWidget(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], ['verify']);

        $html = $this->integration->renderPhoneTag($tag);

        $this->assertStringContainsString('data-wsms-verify="1"', $html);
        $this->assertStringContainsString('wsms-verify-widget-container', $html);
        $this->assertStringContainsString('wsms-verified-flag', $html);
    }

    public function testPhoneWithoutVerifyOmitsWidget(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], []);

        $html = $this->integration->renderPhoneTag($tag);

        $this->assertStringNotContainsString('data-wsms-verify="1"', $html);
        $this->assertStringNotContainsString('wsms-verify-widget-container', $html);
        $this->assertStringNotContainsString('wsms-verified-flag', $html);
    }

    public function testPhoneRequiredSetsDataAttribute(): void
    {
        $tag = new FakeFormTag('my-phone', true, [], []);

        $html = $this->integration->renderPhoneTag($tag);

        $this->assertStringContainsString('data-wsms-required="1"', $html);
    }

    public function testPhoneWrapperHasDataName(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], []);

        $html = $this->integration->renderPhoneTag($tag);

        $this->assertStringContainsString('data-name="my-phone"', $html);
    }

    public function testPhoneDoesNotRenderVisibleInput(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], []);

        $html = $this->integration->renderPhoneTag($tag);

        // No <input type="tel"> — lite-phone-input creates it
        $this->assertStringNotContainsString('type="tel"', $html);
        $this->assertStringNotContainsString('<input', $html);
    }

    public function testPhoneWithVerifyRendersHiddenInputForFlag(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], ['verify']);

        $html = $this->integration->renderPhoneTag($tag);

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="wsms_verified_my-phone"', $html);
    }

    // ── Phone validation tests ─────────────────────────────────────

    public function testValidE164Passes(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], []);
        $result = new FakeValidationResult();
        $_POST['my-phone'] = '+12025551234';

        $returned = $this->integration->validatePhone($result, $tag);

        $this->assertFalse($returned->isInvalidated);
    }

    public function testValidLongE164Passes(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], []);
        $result = new FakeValidationResult();
        $_POST['my-phone'] = '+447911123456';

        $returned = $this->integration->validatePhone($result, $tag);

        $this->assertFalse($returned->isInvalidated);
    }

    public function testValidShortE164Passes(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], []);
        $result = new FakeValidationResult();
        $_POST['my-phone'] = '+6771234';

        $returned = $this->integration->validatePhone($result, $tag);

        $this->assertFalse($returned->isInvalidated);
    }

    public function testInvalidNoPlus(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], []);
        $result = new FakeValidationResult();
        $_POST['my-phone'] = '12025551234';

        $returned = $this->integration->validatePhone($result, $tag);

        $this->assertTrue($returned->isInvalidated);
        $this->assertStringContainsString('valid phone', $returned->invalidMessage);
    }

    public function testInvalidPlusOnly(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], []);
        $result = new FakeValidationResult();
        $_POST['my-phone'] = '+';

        $returned = $this->integration->validatePhone($result, $tag);

        $this->assertTrue($returned->isInvalidated);
    }

    public function testInvalidLetters(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], []);
        $result = new FakeValidationResult();
        $_POST['my-phone'] = '+1abc2345';

        $returned = $this->integration->validatePhone($result, $tag);

        $this->assertTrue($returned->isInvalidated);
    }

    public function testInvalidTooShort(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], []);
        $result = new FakeValidationResult();
        $_POST['my-phone'] = '+1';

        $returned = $this->integration->validatePhone($result, $tag);

        $this->assertTrue($returned->isInvalidated);
    }

    public function testRequiredEmpty(): void
    {
        $tag = new FakeFormTag('my-phone', true, [], []);
        $result = new FakeValidationResult();
        $_POST['my-phone'] = '';

        $returned = $this->integration->validatePhone($result, $tag);

        $this->assertTrue($returned->isInvalidated);
        $this->assertStringContainsString('required', $returned->invalidMessage);
    }

    public function testOptionalEmpty(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], []);
        $result = new FakeValidationResult();
        $_POST['my-phone'] = '';

        $returned = $this->integration->validatePhone($result, $tag);

        $this->assertFalse($returned->isInvalidated);
    }

    public function testVerifyAndVerified(): void
    {
        $this->service->method('isVerified')->willReturn(true);

        $tag = new FakeFormTag('my-phone', false, [], ['verify']);
        $result = new FakeValidationResult();
        $_POST['my-phone'] = '+12025551234';
        $_POST['wsms_verified_my-phone'] = 'valid-session-token';

        $returned = $this->integration->validatePhone($result, $tag);

        $this->assertFalse($returned->isInvalidated);
    }

    public function testVerifyAndNotVerified(): void
    {
        $this->service->method('isVerified')->willReturn(false);

        $tag = new FakeFormTag('my-phone', false, [], ['verify']);
        $result = new FakeValidationResult();
        $_POST['my-phone'] = '+12025551234';
        $_POST['wsms_verified_my-phone'] = 'invalid-token';

        $returned = $this->integration->validatePhone($result, $tag);

        $this->assertTrue($returned->isInvalidated);
        $this->assertStringContainsString('phone', strtolower($returned->invalidMessage));
    }

    public function testVerifyNoToken(): void
    {
        $tag = new FakeFormTag('my-phone', false, [], ['verify']);
        $result = new FakeValidationResult();
        $_POST['my-phone'] = '+12025551234';
        $_POST['wsms_verified_my-phone'] = '';

        $returned = $this->integration->validatePhone($result, $tag);

        $this->assertTrue($returned->isInvalidated);
    }

    public function testNoVerifyServiceNotCalled(): void
    {
        $this->service->expects($this->never())->method('isVerified');

        $tag = new FakeFormTag('my-phone', false, [], []);
        $result = new FakeValidationResult();
        $_POST['my-phone'] = '+12025551234';

        $this->integration->validatePhone($result, $tag);
    }

    // ── Email validation tests (unchanged) ─────────────────────────

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
        $this->assertStringContainsString('email', strtolower($returned->invalidMessage));
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

    // ── Message registration tests ─────────────────────────────────

    public function testRegisterMessagesAddsAllMessages(): void
    {
        $messages = $this->integration->registerMessages([]);

        $this->assertArrayHasKey('wsms_verify_required', $messages);
        $this->assertArrayHasKey('wsms_verify_email_not_verified', $messages);
        $this->assertArrayHasKey('wsms_verify_phone_not_verified', $messages);
        $this->assertArrayHasKey('wsms_phone_required', $messages);
        $this->assertArrayHasKey('wsms_phone_invalid', $messages);

        // Old combined key should not exist
        $this->assertArrayNotHasKey('wsms_verify_not_verified', $messages);

        // No %s placeholders in any message defaults
        foreach ($messages as $msg) {
            $this->assertStringNotContainsString('%s', $msg['default']);
        }
    }

    public function testRegisterMessagesPreservesExistingMessages(): void
    {
        $existing = ['some_message' => ['description' => 'test', 'default' => 'Test']];
        $messages = $this->integration->registerMessages($existing);

        $this->assertArrayHasKey('some_message', $messages);
        $this->assertArrayHasKey('wsms_verify_required', $messages);
        $this->assertArrayHasKey('wsms_phone_required', $messages);
    }

    protected function tearDown(): void
    {
        unset(
            $_POST['your-email'], $_POST['wsms_verified_your-email'],
            $_POST['my-phone'], $_POST['wsms_verified_my-phone'],
        );
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
