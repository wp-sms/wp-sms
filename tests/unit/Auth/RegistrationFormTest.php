<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\RegistrationForm;

class RegistrationFormTest extends TestCase
{
    public function testUlidGeneratedWhenIdEmpty(): void
    {
        $form = new RegistrationForm(
            id: '',
            name: 'Test Form',
            slug: 'test-form',
        );

        $this->assertNotEmpty($form->getId());
        $this->assertSame(26, strlen($form->getId())); // ULID length
    }

    public function testPreservesExplicitId(): void
    {
        $form = new RegistrationForm(
            id: 'EXISTING_ID',
            name: 'Test Form',
            slug: 'test-form',
        );

        $this->assertSame('EXISTING_ID', $form->getId());
    }

    public function testDefaultTimestamps(): void
    {
        $form = new RegistrationForm(
            id: '',
            name: 'Test',
            slug: 'test',
        );

        $this->assertNotNull($form->getCreatedAt());
        $this->assertNotNull($form->getUpdatedAt());
        $this->assertSame($form->getCreatedAt(), $form->getUpdatedAt());
    }

    public function testGetters(): void
    {
        $form = new RegistrationForm(
            id: 'ABC123',
            name: 'Vendor Form',
            slug: 'vendor-form',
            fields: [
                ['id' => 'email', 'required' => true, 'sort_order' => 1],
                ['id' => 'phone', 'required' => false, 'sort_order' => 2],
            ],
            authOverrides: ['email' => ['verify_at_signup' => false]],
            userRole: 'vendor',
            redirectUrl: '/welcome',
            branding: ['primary_color' => '#ff0000'],
            status: 'draft',
            description: 'Vendor registration',
            createdBy: 1,
        );

        $this->assertSame('ABC123', $form->getId());
        $this->assertSame('Vendor Form', $form->getName());
        $this->assertSame('vendor-form', $form->getSlug());
        $this->assertSame('draft', $form->getStatus());
        $this->assertSame('Vendor registration', $form->getDescription());
        $this->assertSame(1, $form->getCreatedBy());
        $this->assertSame('vendor', $form->getUserRole());
        $this->assertSame('/welcome', $form->getRedirectUrl());
        $this->assertSame(['primary_color' => '#ff0000'], $form->getBrandingOverrides());
        $this->assertCount(2, $form->getFields());
        $this->assertSame(['email' => ['verify_at_signup' => false]], $form->getAuthOverrides());
    }

    public function testGetFieldIds(): void
    {
        $form = new RegistrationForm(
            id: '',
            name: 'Test',
            slug: 'test',
            fields: [
                ['id' => 'email', 'required' => true, 'sort_order' => 1],
                ['id' => 'password', 'required' => true, 'sort_order' => 2],
                ['id' => 'phone', 'required' => false, 'sort_order' => 3],
            ],
        );

        $this->assertSame(['email', 'password', 'phone'], $form->getFieldIds());
    }

    public function testIsFieldRequired(): void
    {
        $form = new RegistrationForm(
            id: '',
            name: 'Test',
            slug: 'test',
            fields: [
                ['id' => 'email', 'required' => true, 'sort_order' => 1],
                ['id' => 'phone', 'required' => false, 'sort_order' => 2],
            ],
        );

        $this->assertTrue($form->isFieldRequired('email'));
        $this->assertFalse($form->isFieldRequired('phone'));
        $this->assertFalse($form->isFieldRequired('nonexistent'));
    }

    public function testToArray(): void
    {
        $form = new RegistrationForm(
            id: 'ABC',
            name: 'Test',
            slug: 'test',
            fields: [['id' => 'email', 'required' => true, 'sort_order' => 1]],
            authOverrides: [],
            userRole: 'subscriber',
            redirectUrl: '/thanks',
            branding: [],
            status: 'active',
            description: 'A test form',
            createdBy: 1,
            createdAt: '2026-01-01T00:00:00+00:00',
            updatedAt: '2026-01-01T00:00:00+00:00',
        );

        $arr = $form->toArray();

        $this->assertSame('ABC', $arr['id']);
        $this->assertSame('Test', $arr['name']);
        $this->assertSame('test', $arr['slug']);
        $this->assertSame('A test form', $arr['description']);
        $this->assertSame('active', $arr['status']);
        $this->assertSame('subscriber', $arr['user_role']);
        $this->assertSame('/thanks', $arr['redirect_url']);
        $this->assertSame(1, $arr['created_by']);
        $this->assertCount(1, $arr['fields']);
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => 'XYZ',
            'name' => 'From Array',
            'slug' => 'from-array',
            'description' => 'Test description',
            'status' => 'draft',
            'fields' => [
                ['id' => 'email', 'required' => true, 'sort_order' => 1],
            ],
            'auth_overrides' => ['phone' => ['verify_at_signup' => false]],
            'user_role' => 'editor',
            'redirect_url' => '/done',
            'branding' => ['primary_color' => '#123456'],
            'created_by' => 5,
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-02T00:00:00+00:00',
        ];

        $form = RegistrationForm::fromArray($data);

        $this->assertSame('XYZ', $form->getId());
        $this->assertSame('From Array', $form->getName());
        $this->assertSame('from-array', $form->getSlug());
        $this->assertSame('draft', $form->getStatus());
        $this->assertSame('editor', $form->getUserRole());
        $this->assertSame(5, $form->getCreatedBy());
        $this->assertSame(['phone' => ['verify_at_signup' => false]], $form->getAuthOverrides());
    }

    public function testFromArrayWithDefaults(): void
    {
        $form = RegistrationForm::fromArray(['name' => 'Minimal']);

        $this->assertNotEmpty($form->getId()); // ULID generated
        $this->assertSame('Minimal', $form->getName());
        $this->assertSame('', $form->getSlug());
        $this->assertSame('active', $form->getStatus());
        $this->assertSame('', $form->getUserRole());
        $this->assertSame([], $form->getFields());
    }

    public function testApplyOverridesSkipsDisabledChannel(): void
    {
        $settings = [
            'email' => ['enabled' => true, 'verify_at_signup' => false],
            'phone' => ['enabled' => false, 'verify_at_signup' => false],
        ];

        $result = RegistrationForm::applyOverrides($settings, [
            'phone' => ['verify_at_signup' => true],
        ]);

        $this->assertFalse($result['phone']['verify_at_signup']);
    }

    public function testApplyOverridesAllowsEnableOnEnabledChannel(): void
    {
        $settings = [
            'email' => ['enabled' => true, 'verify_at_signup' => false],
        ];

        $result = RegistrationForm::applyOverrides($settings, [
            'email' => ['verify_at_signup' => true],
        ]);

        $this->assertTrue($result['email']['verify_at_signup']);
    }

    public function testApplyOverridesAllowsDisableOnEnabledChannel(): void
    {
        $settings = [
            'email' => ['enabled' => true, 'verify_at_signup' => true],
        ];

        $result = RegistrationForm::applyOverrides($settings, [
            'email' => ['verify_at_signup' => false],
        ]);

        $this->assertFalse($result['email']['verify_at_signup']);
    }

    public function testApplyOverridesIgnoresNonAllowedKeys(): void
    {
        $settings = [
            'email' => ['enabled' => true, 'usage' => 'login', 'verify_at_signup' => false],
        ];

        $result = RegistrationForm::applyOverrides($settings, [
            'email' => ['usage' => 'mfa', 'enabled' => false],
        ]);

        $this->assertSame('login', $result['email']['usage']);
        $this->assertTrue($result['email']['enabled']);
    }

    public function testRoundTrip(): void
    {
        $original = new RegistrationForm(
            id: 'ROUNDTRIP',
            name: 'Round Trip',
            slug: 'round-trip',
            fields: [
                ['id' => 'email', 'required' => true, 'sort_order' => 1],
                ['id' => 'password', 'required' => true, 'sort_order' => 2],
            ],
            authOverrides: ['email' => ['verify_at_signup' => false]],
            userRole: 'subscriber',
            redirectUrl: '/welcome',
            branding: ['primary_color' => '#aabbcc'],
            status: 'active',
            description: 'Test',
            createdBy: 1,
            createdAt: '2026-01-01T00:00:00+00:00',
            updatedAt: '2026-01-01T12:00:00+00:00',
        );

        $restored = RegistrationForm::fromArray($original->toArray());

        $this->assertSame($original->getId(), $restored->getId());
        $this->assertSame($original->getName(), $restored->getName());
        $this->assertSame($original->getSlug(), $restored->getSlug());
        $this->assertSame($original->getStatus(), $restored->getStatus());
        $this->assertSame($original->getUserRole(), $restored->getUserRole());
        $this->assertSame($original->getRedirectUrl(), $restored->getRedirectUrl());
        $this->assertSame($original->getFields(), $restored->getFields());
        $this->assertSame($original->getAuthOverrides(), $restored->getAuthOverrides());
        $this->assertSame($original->getBrandingOverrides(), $restored->getBrandingOverrides());
    }
}
