<?php

namespace WSms\Tests\Unit\Verification\Plugin;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Verification\Plugin\WPForms\VerifyEmailField;
use WSms\Verification\Plugin\WPForms\WPFormsVerifyField;
use WSms\Verification\VerificationService;

class WPFormsVerifyEmailFieldTest extends TestCase
{
    private VerifyEmailField $field;
    private MockObject&VerificationService $service;

    protected function setUp(): void
    {
        $this->service = $this->createMock(VerificationService::class);
        $this->field = new VerifyEmailField($this->service);

        // Reset shared state.
        wpforms()->obj('process')->errors = [];
        wpforms()->obj('process')->fields = [];
        WPFormsVerifyField::$assetsEnqueued = false;
    }

    public function testInitSetsCorrectProperties(): void
    {
        $this->assertSame('wsms_verify_email', $this->field->type);
        $this->assertSame('fa-envelope-o', $this->field->icon);
        $this->assertSame('fancy', $this->field->group);
        $this->assertSame(300, $this->field->order);
    }

    public function testValidatePassesWhenVerified(): void
    {
        $this->service->method('isVerified')->willReturn(true);

        $_POST['wpforms'] = ['wsms_verified' => [1 => 'valid-token']];

        $this->field->validate(1, 'test@example.com', $this->formData());

        $this->assertEmpty(wpforms()->obj('process')->errors);
    }

    public function testValidateFailsWhenNotVerified(): void
    {
        $this->service->method('isVerified')->willReturn(false);

        $_POST['wpforms'] = ['wsms_verified' => [1 => 'invalid-token']];

        $this->field->validate(1, 'test@example.com', $this->formData());

        $this->assertArrayHasKey(1, wpforms()->obj('process')->errors[100]);
        $this->assertStringContainsString('verify', wpforms()->obj('process')->errors[100][1]);
    }

    public function testValidateFailsWhenNoSessionToken(): void
    {
        $_POST['wpforms'] = ['wsms_verified' => [1 => '']];

        $this->field->validate(1, 'test@example.com', $this->formData());

        $this->assertArrayHasKey(1, wpforms()->obj('process')->errors[100]);
    }

    public function testValidatePassesWhenOptionalAndEmpty(): void
    {
        $formData = $this->formData(required: false);

        $this->field->validate(1, '', $formData);

        $this->assertEmpty(wpforms()->obj('process')->errors);
    }

    public function testValidateFailsWhenRequiredAndEmpty(): void
    {
        $formData = $this->formData(required: true);

        $this->field->validate(1, '', $formData);

        $this->assertArrayHasKey(1, wpforms()->obj('process')->errors[100]);
        $this->assertStringContainsString('required', wpforms()->obj('process')->errors[100][1]);
    }

    public function testValidateCallsServiceWithCorrectChannel(): void
    {
        $this->service->expects($this->once())
            ->method('isVerified')
            ->with('email', 'test@example.com', 'my-token')
            ->willReturn(true);

        $_POST['wpforms'] = ['wsms_verified' => [1 => 'my-token']];

        $this->field->validate(1, 'test@example.com', $this->formData());
    }

    public function testFormatStoresCorrectData(): void
    {
        $this->field->format(1, 'test@example.com', $this->formData());

        $stored = wpforms()->obj('process')->fields[1];
        $this->assertSame('Your Email', $stored['name']);
        $this->assertSame('test@example.com', $stored['value']);
        $this->assertSame(1, $stored['id']);
        $this->assertSame('wsms_verify_email', $stored['type']);
    }

    public function testFormatUsesEmailSanitizer(): void
    {
        $this->field->format(1, 'bad chars<>@example.com', $this->formData());

        $stored = wpforms()->obj('process')->fields[1];
        // sanitize_email strips illegal characters
        $this->assertStringNotContainsString('<', $stored['value']);
        $this->assertStringNotContainsString('>', $stored['value']);
    }

    private function formData(bool $required = true): array
    {
        return [
            'id'     => 100,
            'fields' => [
                1 => [
                    'label'    => 'Your Email',
                    'required' => $required ? '1' : '',
                ],
            ],
        ];
    }

    protected function tearDown(): void
    {
        unset($_POST['wpforms']);
    }
}
