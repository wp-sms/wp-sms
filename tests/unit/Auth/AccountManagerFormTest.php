<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Auth\AccountManager;
use WSms\Auth\AuthSession;
use WSms\Auth\RegistrationForm;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\MessageDispatcher;
use WSms\Mfa\MfaManager;
use WSms\Auth\SettingsRepository;
use WSms\Messaging\Template\TemplateManager;
use WSms\Verification\OtpService;

class AccountManagerFormTest extends TestCase
{
    private AccountManager $manager;
    private MockObject&AuditLogger $auditLogger;
    private MockObject&OtpService $otpService;
    private MockObject&AuthSession $authSession;

    protected function setUp(): void
    {
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->otpService = $this->createMock(OtpService::class);
        $this->authSession = $this->createMock(AuthSession::class);

        $this->manager = new AccountManager(
            $this->auditLogger,
            $this->otpService,
            $this->createMock(MfaManager::class),
            $this->authSession,
            new SettingsRepository(),
            (function () {
                $d = $this->createMock(MessageDispatcher::class);
                $d->method('sendImmediate')->willReturn(DeliveryResult::sent());
                return $d;
            })(),
            $this->createMock(TemplateManager::class),
        );

        $this->otpService->method('createToken')->willReturn('test-token-abc');
        $this->otpService->method('createOtp')->willReturn('123456');
        $this->authSession->method('create')->willReturn('reg-session-token');

        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'password' => ['enabled' => true, 'required_at_signup' => true],
            'email'    => ['enabled' => true, 'required_at_signup' => true, 'verify_at_signup' => false],
            'phone'    => ['enabled' => true, 'required_at_signup' => false, 'verify_at_signup' => false],
        ];

        unset(
            $GLOBALS['_test_wp_insert_user_result'],
            $GLOBALS['_test_wp_insert_user_data'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_get_user_by_result'],
            $GLOBALS['_test_current_user_id'],
        );
        $GLOBALS['_test_user_meta'] = [];
        $GLOBALS['_test_do_action_calls'] = [];
        $GLOBALS['_test_wp_roles'] = ['subscriber' => 'Subscriber', 'vendor' => 'Vendor', 'editor' => 'Editor'];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_insert_user_result'],
            $GLOBALS['_test_wp_insert_user_data'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_get_user_by_result'],
            $GLOBALS['_test_current_user_id'],
            $GLOBALS['_test_user_meta'],
            $GLOBALS['_test_do_action_calls'],
            $GLOBALS['_test_wp_roles'],
            $GLOBALS['_test_options'],
        );
    }

    private function makeForm(array $overrides = []): RegistrationForm
    {
        return new RegistrationForm(
            id: $overrides['id'] ?? 'FORM001',
            name: $overrides['name'] ?? 'Test Form',
            slug: $overrides['slug'] ?? 'test-form',
            fields: $overrides['fields'] ?? [
                ['id' => 'email', 'required' => true, 'sort_order' => 1],
                ['id' => 'password', 'required' => true, 'sort_order' => 2],
            ],
            authOverrides: $overrides['auth_overrides'] ?? [],
            userRole: $overrides['user_role'] ?? 'vendor',
            redirectUrl: $overrides['redirect_url'] ?? '',
            branding: $overrides['branding'] ?? [],
        );
    }

    public function testRegisterWithFormAssignsFormRole(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 42;
        $form = $this->makeForm(['user_role' => 'vendor']);

        $result = $this->manager->registerUser([
            'email'    => 'vendor@example.com',
            'password' => 'StrongPass1!',
        ], false, $form);

        $this->assertTrue($result->success);
        // Check that userdata had role set
        $this->assertSame('vendor', $GLOBALS['_test_wp_insert_user_data']['role'] ?? null);
    }

    public function testRegisterWithFormStoresFormIdInMeta(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 42;
        $form = $this->makeForm();

        $this->manager->registerUser([
            'email'    => 'user@example.com',
            'password' => 'StrongPass1!',
        ], false, $form);

        $this->assertSame(
            'FORM001',
            $GLOBALS['_test_user_meta'][42]['wsms_registration_form_id'] ?? null,
        );
    }

    public function testRegisterWithFormFiresAction(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 42;
        $form = $this->makeForm();

        $this->manager->registerUser([
            'email'    => 'user@example.com',
            'password' => 'StrongPass1!',
        ], false, $form);

        $formActions = array_filter(
            $GLOBALS['_test_do_action_calls'] ?? [],
            fn($call) => ($call['hook'] ?? $call[0] ?? null) === 'wsms_form_registration',
        );

        $this->assertNotEmpty($formActions, 'wsms_form_registration action should have fired');
    }

    public function testRegisterWithoutFormWorksUnchanged(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 42;

        $result = $this->manager->registerUser([
            'email'    => 'regular@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(42, $result->userId);
        // No form_id meta
        $this->assertArrayNotHasKey('wsms_registration_form_id', $GLOBALS['_test_user_meta'][42] ?? []);
    }

    public function testRegisterWithFormRedirectUrl(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 42;
        $form = $this->makeForm(['redirect_url' => '/welcome-vendor']);

        $result = $this->manager->registerUser([
            'email'    => 'vendor@example.com',
            'password' => 'StrongPass1!',
        ], false, $form);

        $this->assertTrue($result->success);
        $this->assertSame('/welcome-vendor', $result->meta['redirect_url'] ?? null);
    }

    public function testRegisterWithFormNoRedirectUrlOmitsIt(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 42;
        $form = $this->makeForm(['redirect_url' => '']);

        $result = $this->manager->registerUser([
            'email'    => 'vendor@example.com',
            'password' => 'StrongPass1!',
        ], false, $form);

        $this->assertArrayNotHasKey('redirect_url', $result->meta);
    }

    public function testFormFieldValidationOnlyRequiresFormFields(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 42;

        // Global settings require email and password, but form only has email
        $form = $this->makeForm([
            'fields' => [
                ['id' => 'email', 'required' => true, 'sort_order' => 1],
            ],
        ]);

        // Missing password should be OK since it's not in the form's field list
        $result = $this->manager->registerUser([
            'email' => 'user@example.com',
        ], false, $form);

        $this->assertTrue($result->success);
    }

    public function testFormFieldWithRequiredTrueIsRequired(): void
    {
        $form = $this->makeForm([
            'fields' => [
                ['id' => 'email', 'required' => true, 'sort_order' => 1],
                ['id' => 'first_name', 'required' => true, 'sort_order' => 2],
            ],
        ]);

        $result = $this->manager->registerUser([
            'email' => 'user@example.com',
            // Missing required first_name
        ], false, $form);

        $this->assertFalse($result->success);
        $this->assertSame('missing_first_name', $result->error);
    }

    public function testFormFieldNotInFormIsNotRequired(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 42;

        // Global settings have first_name in registration_fields
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = array_merge(
            $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY],
            ['registration_fields' => ['email', 'password', 'first_name']],
        );

        // But form does NOT include first_name
        $form = $this->makeForm([
            'fields' => [
                ['id' => 'email', 'required' => true, 'sort_order' => 1],
                ['id' => 'password', 'required' => true, 'sort_order' => 2],
            ],
        ]);

        $result = $this->manager->registerUser([
            'email'    => 'user@example.com',
            'password' => 'StrongPass1!',
            // No first_name — should be OK
        ], false, $form);

        $this->assertTrue($result->success);
    }

    public function testFormDisablesVerifyAtSignup(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 42;

        // Global: email verify at signup enabled
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'password' => ['enabled' => true, 'required_at_signup' => true],
            'email'    => ['enabled' => true, 'required_at_signup' => true, 'verify_at_signup' => true],
            'phone'    => ['enabled' => false],
        ];

        $form = $this->makeForm([
            'auth_overrides' => [
                'email' => ['verify_at_signup' => false],
            ],
        ]);

        $result = $this->manager->registerUser([
            'email'    => 'user@example.com',
            'password' => 'StrongPass1!',
        ], false, $form);

        $this->assertTrue($result->success);
        // No pending verifications since email verify was disabled by form
        $this->assertEmpty($result->meta['pending_verifications'] ?? []);
    }

    public function testFormWithoutPhoneFieldSkipsPhoneVerification(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 42;

        // Global: phone verify at signup enabled
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'password' => ['enabled' => true, 'required_at_signup' => true],
            'email'    => ['enabled' => true, 'required_at_signup' => true, 'verify_at_signup' => false],
            'phone'    => ['enabled' => true, 'required_at_signup' => false, 'verify_at_signup' => true],
        ];

        // Form only has email + password — no phone field
        $form = $this->makeForm([
            'fields' => [
                ['id' => 'email', 'required' => true, 'sort_order' => 1],
                ['id' => 'password', 'required' => true, 'sort_order' => 2],
            ],
        ]);

        $result = $this->manager->registerUser([
            'email'    => 'user@example.com',
            'password' => 'StrongPass1!',
            'phone'    => '+1234567890', // Phone data submitted despite not being in form
        ], false, $form);

        $this->assertTrue($result->success);
        // No phone pending verification even though global setting enables it
        $pendingChannels = array_column($result->meta['pending_verifications'] ?? [], 'channel');
        $this->assertNotContains('phone', $pendingChannels);
    }

    public function testFormWithInvalidRoleFallsBackToDefault(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 42;
        $GLOBALS['_test_wp_roles'] = ['subscriber' => 'Subscriber']; // 'vendor' not in roles

        $form = $this->makeForm(['user_role' => 'deleted_role']);

        $result = $this->manager->registerUser([
            'email'    => 'user@example.com',
            'password' => 'StrongPass1!',
        ], false, $form);

        $this->assertTrue($result->success);
        // role should NOT be set in userdata (falls back to WP default)
        $this->assertArrayNotHasKey('role', $GLOBALS['_test_wp_insert_user_data'] ?? []);
    }
}
