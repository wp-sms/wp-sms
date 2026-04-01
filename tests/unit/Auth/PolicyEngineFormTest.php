<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\PolicyEngine;
use WSms\Auth\RegistrationForm;
use WSms\Auth\SettingsRepository;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\MfaManager;
use WSms\Mfa\UserFactorRepository;

class PolicyEngineFormTest extends TestCase
{
    private PolicyEngine $engine;
    private MfaManager $mfaManager;

    protected function setUp(): void
    {
        $this->mfaManager = new MfaManager($this->createMock(UserFactorRepository::class));
        $this->mfaManager->registerChannel($this->makeChannel('phone', supportsPrimary: true, supportsMfa: true));
        $this->mfaManager->registerChannel($this->makeChannel('email', supportsPrimary: true, supportsMfa: true));
        $this->mfaManager->registerChannel($this->makeChannel('backup_codes', supportsPrimary: false, supportsMfa: true));

        $this->engine = new PolicyEngine($this->mfaManager, new SettingsRepository());

        unset($GLOBALS['_test_options'][SettingsRepository::OPTION_KEY]);
    }

    // --- resolveFormConfig ---

    public function testResolveFormConfigInheritsGlobalSettings(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'email' => ['enabled' => true, 'verify_at_signup' => true],
            'phone' => ['enabled' => true, 'verify_at_signup' => true],
        ];

        $form = new RegistrationForm(
            id: 'F1',
            name: 'Test',
            slug: 'test',
            authOverrides: [], // No overrides
        );

        $engine = new PolicyEngine($this->mfaManager, new SettingsRepository());
        $resolved = $engine->resolveFormConfig($form);

        $this->assertTrue($resolved['email']['verify_at_signup']);
        $this->assertTrue($resolved['phone']['verify_at_signup']);
    }

    public function testResolveFormConfigCanDisableVerifyAtSignup(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'email' => ['enabled' => true, 'verify_at_signup' => true],
            'phone' => ['enabled' => true, 'verify_at_signup' => true],
        ];

        $form = new RegistrationForm(
            id: 'F2',
            name: 'Test',
            slug: 'test',
            authOverrides: [
                'email' => ['verify_at_signup' => false],
            ],
        );

        $engine = new PolicyEngine($this->mfaManager, new SettingsRepository());
        $resolved = $engine->resolveFormConfig($form);

        $this->assertFalse($resolved['email']['verify_at_signup']);
        $this->assertTrue($resolved['phone']['verify_at_signup']); // Unchanged
    }

    public function testResolveFormConfigCannotEnableDisabledChannel(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'phone' => ['enabled' => false, 'verify_at_signup' => false],
        ];

        $form = new RegistrationForm(
            id: 'F3',
            name: 'Test',
            slug: 'test',
            authOverrides: [
                'phone' => ['verify_at_signup' => true], // Trying to enable — should be ignored
            ],
        );

        $engine = new PolicyEngine($this->mfaManager, new SettingsRepository());
        $resolved = $engine->resolveFormConfig($form);

        $this->assertFalse($resolved['phone']['verify_at_signup']);
    }

    public function testResolveFormConfigCanEnableVerifyOnEnabledChannel(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'email' => ['enabled' => true, 'verify_at_signup' => false],
        ];

        $form = new RegistrationForm(
            id: 'F3b',
            name: 'Test',
            slug: 'test',
            authOverrides: [
                'email' => ['verify_at_signup' => true],
            ],
        );

        $engine = new PolicyEngine($this->mfaManager, new SettingsRepository());
        $resolved = $engine->resolveFormConfig($form);

        $this->assertTrue($resolved['email']['verify_at_signup']);
    }

    public function testResolveFormConfigIgnoresNonAllowedKeys(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'email' => ['enabled' => true, 'usage' => 'login', 'verify_at_signup' => true],
        ];

        $form = new RegistrationForm(
            id: 'F4',
            name: 'Test',
            slug: 'test',
            authOverrides: [
                'email' => ['usage' => 'mfa'], // Not allowed — should be ignored
            ],
        );

        $engine = new PolicyEngine($this->mfaManager, new SettingsRepository());
        $resolved = $engine->resolveFormConfig($form);

        $this->assertSame('login', $resolved['email']['usage']);
    }

    // --- getFormVerificationRequirements ---

    public function testFormVerificationRequirementsReflectsOverrides(): void
    {
        $GLOBALS['_test_options'][SettingsRepository::OPTION_KEY] = [
            'email' => ['enabled' => true, 'verify_at_signup' => true],
            'phone' => ['enabled' => true, 'verify_at_signup' => true],
        ];

        $form = new RegistrationForm(
            id: 'F5',
            name: 'Test',
            slug: 'test',
            authOverrides: [
                'phone' => ['verify_at_signup' => false],
            ],
        );

        $engine = new PolicyEngine($this->mfaManager, new SettingsRepository());
        $reqs = $engine->getFormVerificationRequirements($form);

        $this->assertTrue($reqs['require_email_verification']);
        $this->assertFalse($reqs['require_phone_verification']);
    }

    // --- getFormEffectiveRegistrationFields ---

    public function testFormEffectiveFieldsReturnsFormFieldIds(): void
    {
        $form = new RegistrationForm(
            id: 'F6',
            name: 'Test',
            slug: 'test',
            fields: [
                ['id' => 'email', 'required' => true, 'sort_order' => 1],
                ['id' => 'first_name', 'required' => true, 'sort_order' => 2],
            ],
        );

        // Without field registry, returns raw field IDs
        $fields = $this->engine->getFormEffectiveRegistrationFields($form);
        $this->assertSame(['email', 'first_name'], $fields);
    }

    private function makeChannel(string $id, bool $supportsPrimary = false, bool $supportsMfa = false): ChannelInterface
    {
        return new class($id, $supportsPrimary, $supportsMfa) implements ChannelInterface {
            public function __construct(
                private string $id,
                private bool $supportsPrimary,
                private bool $supportsMfa,
            ) {
            }

            public function getId(): string { return $this->id; }
            public function getName(): string { return ucfirst($this->id); }
            public function supportsPrimaryAuth(): bool { return $this->supportsPrimary; }
            public function supportsMfa(): bool { return $this->supportsMfa; }
            public function supportsAutoEnrollment(): bool { return $this->id === 'email'; }
            public function getEnabledSettingKey(): string { return 'enabled'; }

            public function isAvailableForUser(int $userId): bool
            {
                if ($this->id === 'phone') {
                    return !empty(get_user_meta($userId, 'wsms_phone', true));
                }
                if ($this->id === 'email') {
                    $email = get_userdata($userId)?->user_email ?? '';
                    return !empty($email) && !str_ends_with($email, '@noreply.wsms.local');
                }
                return true;
            }

            public function enroll(int $userId, array $data): \WSms\Mfa\ValueObjects\EnrollmentResult { return new \WSms\Mfa\ValueObjects\EnrollmentResult(true, 'OK'); }
            public function sendChallenge(int $userId, array $context = []): \WSms\Mfa\ValueObjects\ChallengeResult { return new \WSms\Mfa\ValueObjects\ChallengeResult(true, 'OK'); }
            public function verify(int $userId, string $code, array $context = []): bool { return true; }
            public function unenroll(int $userId): bool { return true; }
            public function isEnrolled(int $userId): bool { return false; }
            public function getEnrollmentInfo(int $userId): array { return []; }
        };
    }
}
