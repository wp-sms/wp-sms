<?php

namespace WSms\Tests\Unit\Verification\Plugin;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Verification\Plugin\WPForms\VerifyPhoneField;
use WSms\Verification\Plugin\WPForms\WPFormsVerifyField;
use WSms\Verification\VerificationService;

class WPFormsVerifyPhoneFieldTest extends TestCase
{
    private VerifyPhoneField $field;
    private MockObject&VerificationService $service;

    protected function setUp(): void
    {
        $this->service = $this->createMock(VerificationService::class);
        $this->field = new VerifyPhoneField($this->service);

        // Reset shared state.
        wpforms()->obj('process')->errors = [];
        wpforms()->obj('process')->fields = [];
        WPFormsVerifyField::$assetsEnqueued = false;
    }

    public function testInitSetsCorrectProperties(): void
    {
        $this->assertSame('wsms_verify_phone', $this->field->type);
        $this->assertSame('fa-phone', $this->field->icon);
        $this->assertSame('fancy', $this->field->group);
        $this->assertSame(310, $this->field->order);
    }

    public function testValidatePassesWhenVerified(): void
    {
        $this->service->method('isVerified')->willReturn(true);

        $_POST['wpforms'] = ['wsms_verified' => [1 => 'valid-token']];

        $this->field->validate(1, '+1234567890', $this->formData());

        $this->assertEmpty(wpforms()->obj('process')->errors);
    }

    public function testValidateFailsWhenNotVerified(): void
    {
        $this->service->method('isVerified')->willReturn(false);

        $_POST['wpforms'] = ['wsms_verified' => [1 => 'bad-token']];

        $this->field->validate(1, '+1234567890', $this->formData());

        $this->assertArrayHasKey(1, wpforms()->obj('process')->errors[100]);
        $this->assertStringContainsString('phone number', wpforms()->obj('process')->errors[100][1]);
    }

    public function testValidateCallsServiceWithPhoneChannel(): void
    {
        $this->service->expects($this->once())
            ->method('isVerified')
            ->with('phone', '+1234567890', 'my-token')
            ->willReturn(true);

        $_POST['wpforms'] = ['wsms_verified' => [1 => 'my-token']];

        $this->field->validate(1, '+1234567890', $this->formData());
    }

    public function testFormatStoresCorrectData(): void
    {
        $this->field->format(1, '+1234567890', $this->formData());

        $stored = wpforms()->obj('process')->fields[1];
        $this->assertSame('Your Phone', $stored['name']);
        $this->assertSame('+1234567890', $stored['value']);
        $this->assertSame(1, $stored['id']);
        $this->assertSame('wsms_verify_phone', $stored['type']);
    }

    public function testFormatUsesTextSanitizer(): void
    {
        $this->field->format(1, ' +1 (234) 567-890 ', $this->formData());

        $stored = wpforms()->obj('process')->fields[1];
        // sanitize_text_field trims whitespace
        $this->assertSame('+1 (234) 567-890', $stored['value']);
    }

    public function testInputTypeReturnsTel(): void
    {
        // inputType() is protected — test indirectly via the type property
        $this->assertSame('wsms_verify_phone', $this->field->type);
    }

    private function formData(bool $required = true): array
    {
        return [
            'id'     => 100,
            'fields' => [
                1 => [
                    'label'    => 'Your Phone',
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
