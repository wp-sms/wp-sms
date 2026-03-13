<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\PolicyEngine;

class PolicyEngineTest extends TestCase
{
    private PolicyEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new PolicyEngine();

        unset(
            $GLOBALS['_test_options']['wsms_auth_settings'],
            $GLOBALS['_test_userdata'],
        );
        $GLOBALS['_test_user_meta'] = [];
    }

    public function testValidatePolicyConflictsAlwaysReturnsTrue(): void
    {
        $this->assertTrue($this->engine->validatePolicyConflicts('phone', 'phone'));
        $this->assertTrue($this->engine->validatePolicyConflicts('email', 'email'));
        $this->assertTrue($this->engine->validatePolicyConflicts('password', 'phone'));
        $this->assertTrue($this->engine->validatePolicyConflicts('password', 'email'));
    }

    public function testIsMfaRequiredReturnsFalseWhenNoFactorsEnabled(): void
    {
        $this->assertFalse($this->engine->isMfaRequired(1));
    }

    /**
     * @dataProvider primaryMethodsProvider
     */
    public function testGetAvailablePrimaryMethods(array $settings, array $expected): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = $settings;

        $this->assertSame($expected, $this->engine->getAvailablePrimaryMethods());
    }

    public static function primaryMethodsProvider(): iterable
    {
        yield 'default settings returns password and email' => [
            [],
            ['password', 'email'],
        ];

        yield 'only password enabled' => [
            ['password' => ['enabled' => true], 'email' => ['enabled' => false]],
            ['password'],
        ];

        yield 'password and phone' => [
            [
                'password' => ['enabled' => true],
                'phone'    => ['enabled' => true, 'usage' => 'login', 'allow_sign_in' => true],
                'email'    => ['enabled' => false],
            ],
            ['password', 'phone'],
        ];

        yield 'password and email' => [
            [
                'password' => ['enabled' => true],
                'email'    => ['enabled' => true, 'usage' => 'login', 'allow_sign_in' => true],
            ],
            ['password', 'email'],
        ];

        yield 'all enabled' => [
            [
                'password' => ['enabled' => true],
                'phone'    => ['enabled' => true, 'usage' => 'login', 'allow_sign_in' => true],
                'email'    => ['enabled' => true, 'usage' => 'login', 'allow_sign_in' => true],
            ],
            ['password', 'phone', 'email'],
        ];

        yield 'password disabled, email enabled' => [
            [
                'password' => ['enabled' => false],
                'email'    => ['enabled' => true, 'usage' => 'login', 'allow_sign_in' => true],
            ],
            ['email'],
        ];

        yield 'none enabled falls back to password' => [
            [
                'password' => ['enabled' => false],
                'phone'    => ['enabled' => false],
                'email'    => ['enabled' => false],
            ],
            ['password'],
        ];

        yield 'phone mfa usage not primary' => [
            [
                'password' => ['enabled' => true],
                'phone'    => ['enabled' => true, 'usage' => 'mfa', 'allow_sign_in' => true],
                'email'    => ['enabled' => false],
            ],
            ['password'],
        ];

        yield 'allow_sign_in false' => [
            [
                'password' => ['enabled' => true],
                'phone'    => ['enabled' => true, 'usage' => 'login', 'allow_sign_in' => false],
                'email'    => ['enabled' => false],
            ],
            ['password'],
        ];

        yield 'only phone enabled' => [
            [
                'password' => ['enabled' => false],
                'phone'    => ['enabled' => true, 'usage' => 'login', 'allow_sign_in' => true],
                'email'    => ['enabled' => false],
            ],
            ['phone'],
        ];
    }

    public function testUserMethodsDefaultSettingsReturnsPasswordAndEmail(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [];
        $GLOBALS['_test_userdata'] = $this->makeUser(1);

        $methods = $this->engine->getAvailableMethodsForUser(1);
        $methodNames = array_column($methods, 'method');

        $this->assertCount(2, $methods);
        $this->assertContains('password', $methodNames);
        $this->assertContains('email_otp', $methodNames);
    }

    public function testUserMethodsWithPhoneReturnsPhoneOtp(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'password' => ['enabled' => true],
            'phone'    => ['enabled' => true, 'usage' => 'login', 'verification_methods' => ['otp']],
        ];
        $GLOBALS['_test_userdata'] = $this->makeUser(1);
        $GLOBALS['_test_user_meta'][1]['wsms_phone'] = '+1234567890';

        $methodNames = array_column($this->engine->getAvailableMethodsForUser(1), 'method');

        $this->assertContains('password', $methodNames);
        $this->assertContains('phone_otp', $methodNames);
    }

    public function testUserMethodsPhoneOtpAndMagicLink(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'password' => ['enabled' => true],
            'phone'    => ['enabled' => true, 'usage' => 'login', 'verification_methods' => ['otp', 'magic_link']],
        ];
        $GLOBALS['_test_userdata'] = $this->makeUser(1);
        $GLOBALS['_test_user_meta'][1]['wsms_phone'] = '+1234567890';

        $methodNames = array_column($this->engine->getAvailableMethodsForUser(1), 'method');

        $this->assertContains('phone_otp', $methodNames);
        $this->assertContains('phone_magic_link', $methodNames);
    }

    public function testUserMethodsNoPhoneSkipsPhoneMethods(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'password' => ['enabled' => true],
            'phone'    => ['enabled' => true, 'usage' => 'login', 'verification_methods' => ['otp']],
            'email'    => ['enabled' => false],
        ];
        $GLOBALS['_test_userdata'] = $this->makeUser(1);

        $methodNames = array_column($this->engine->getAvailableMethodsForUser(1), 'method');

        $this->assertSame(['password'], $methodNames);
    }

    public function testUserMethodsEmailOtpAndMagicLink(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'password' => ['enabled' => true],
            'email'    => ['enabled' => true, 'usage' => 'login', 'verification_methods' => ['otp', 'magic_link']],
        ];
        $GLOBALS['_test_userdata'] = $this->makeUser(1);

        $methodNames = array_column($this->engine->getAvailableMethodsForUser(1), 'method');

        $this->assertContains('email_otp', $methodNames);
        $this->assertContains('email_magic_link', $methodNames);
    }

    public function testUserMethodsNoEmailSkipsEmailMethods(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'password' => ['enabled' => true],
            'email'    => ['enabled' => true, 'usage' => 'login', 'verification_methods' => ['otp']],
        ];
        $GLOBALS['_test_userdata'] = $this->makeUser(1, '');

        $methodNames = array_column($this->engine->getAvailableMethodsForUser(1), 'method');

        $this->assertSame(['password'], $methodNames);
    }

    public function testUserMethodsAllMethodsAllData(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'password' => ['enabled' => true],
            'phone'    => ['enabled' => true, 'usage' => 'login', 'verification_methods' => ['otp', 'magic_link']],
            'email'    => ['enabled' => true, 'usage' => 'login', 'verification_methods' => ['otp', 'magic_link']],
        ];
        $GLOBALS['_test_userdata'] = $this->makeUser(1);
        $GLOBALS['_test_user_meta'][1]['wsms_phone'] = '+1234567890';

        $this->assertCount(5, $this->engine->getAvailableMethodsForUser(1));
    }

    public function testUserMethodsNonexistentUserReturnsEmpty(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'password' => ['enabled' => true],
        ];

        $this->assertSame([], $this->engine->getAvailableMethodsForUser(999));
    }

    public function testUserMethodsOnlyEmailOtp(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'password' => ['enabled' => false],
            'email'    => ['enabled' => true, 'usage' => 'login', 'verification_methods' => ['otp']],
        ];
        $GLOBALS['_test_userdata'] = $this->makeUser(1);

        $methodNames = array_column($this->engine->getAvailableMethodsForUser(1), 'method');

        $this->assertSame(['email_otp'], $methodNames);
    }

    /**
     * @dataProvider defaultMethodProvider
     */
    public function testGetDefaultMethod(string $identifierType, array $methods, ?string $expected): void
    {
        $this->assertSame($expected, $this->engine->getDefaultMethod($identifierType, $methods));
    }

    public static function defaultMethodProvider(): iterable
    {
        $pw = ['method' => 'password', 'type' => 'password', 'channel' => 'password'];
        $phoneOtp = ['method' => 'phone_otp', 'type' => 'otp', 'channel' => 'phone'];
        $phoneMagic = ['method' => 'phone_magic_link', 'type' => 'magic_link', 'channel' => 'phone'];
        $emailOtp = ['method' => 'email_otp', 'type' => 'otp', 'channel' => 'email'];

        yield 'empty methods returns null' => ['email', [], null];
        yield 'email prefers password' => ['email', [$pw, $emailOtp], 'password'];
        yield 'email no password falls to email_otp' => ['email', [$emailOtp], 'email_otp'];
        yield 'phone prefers phone_otp' => ['phone', [$pw, $phoneOtp], 'phone_otp'];
        yield 'phone no otp falls to magic_link' => ['phone', [$pw, $phoneMagic], 'phone_magic_link'];
        yield 'username prefers password' => ['username', [$pw, $phoneOtp], 'password'];
        yield 'username no password falls to first' => ['username', [$phoneOtp, $emailOtp], 'phone_otp'];
    }

    /**
     * @dataProvider smartMfaDefaultProvider
     */
    public function testGetSmartMfaDefault(string $primaryMethod, array $factors, ?string $expected): void
    {
        $this->assertSame($expected, $this->engine->getSmartMfaDefault($primaryMethod, $factors));
    }

    public static function smartMfaDefaultProvider(): iterable
    {
        $bothFactors = [
            ['channel_id' => 'phone', 'name' => 'Phone'],
            ['channel_id' => 'email', 'name' => 'Email'],
        ];
        $phoneOnly = [['channel_id' => 'phone', 'name' => 'Phone']];

        yield 'empty returns null' => ['password', [], null];
        yield 'password primary prefers phone' => ['password', $bothFactors, 'phone'];
        yield 'phone primary prefers email' => ['phone_otp', $bothFactors, 'email'];
        yield 'email primary prefers phone' => ['email_otp', $bothFactors, 'phone'];
        yield 'single factor returns that' => ['phone_otp', $phoneOnly, 'phone'];
    }

    public function testGetPendingVerificationsReadsEmailVerifyAtSignup(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'email' => ['enabled' => true, 'verify_at_signup' => true],
        ];
        $GLOBALS['_test_userdata'] = $this->makeUser(1);
        $GLOBALS['_test_user_meta'][1] = ['wsms_email_verified' => ''];

        $pending = $this->engine->getPendingVerifications(1);

        $this->assertCount(1, $pending);
        $this->assertSame('email', $pending[0]['type']);
    }

    public function testGetPendingVerificationsReadsPhoneVerifyAtSignup(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'phone' => ['enabled' => true, 'verify_at_signup' => true],
        ];
        $GLOBALS['_test_userdata'] = $this->makeUser(1);
        $GLOBALS['_test_user_meta'][1] = ['wsms_phone' => '+1234567890', 'wsms_phone_verified' => ''];

        $pending = $this->engine->getPendingVerifications(1);

        $this->assertCount(1, $pending);
        $this->assertSame('phone', $pending[0]['type']);
    }

    public function testGetPendingVerificationsEmptyWhenVerifyAtSignupDisabled(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'email' => ['enabled' => true, 'verify_at_signup' => false],
            'phone' => ['enabled' => true, 'verify_at_signup' => false],
        ];
        $GLOBALS['_test_userdata'] = $this->makeUser(1);
        $GLOBALS['_test_user_meta'][1] = ['wsms_phone' => '+1234567890'];

        $pending = $this->engine->getPendingVerifications(1);

        $this->assertSame([], $pending);
    }

    // --- getEffectiveRegistrationFields ---

    /**
     * @dataProvider effectiveRegistrationFieldsProvider
     */
    public function testGetEffectiveRegistrationFields(array $settings, array $expected): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = $settings;

        // PolicyEngine caches settings, need fresh instance.
        $engine = new PolicyEngine();
        $this->assertSame($expected, $engine->getEffectiveRegistrationFields());
    }

    public static function effectiveRegistrationFieldsProvider(): iterable
    {
        yield 'defaults: email and password required' => [
            [],
            ['email', 'password'],
        ];

        yield 'email not required, password required' => [
            [
                'email'    => ['required_at_signup' => false],
                'password' => ['required_at_signup' => true],
                'registration_fields' => ['email', 'password', 'phone'],
            ],
            ['password', 'phone'],
        ];

        yield 'both not required, phone in fields' => [
            [
                'email'    => ['required_at_signup' => false],
                'password' => ['required_at_signup' => false],
                'registration_fields' => ['phone'],
            ],
            ['phone'],
        ];

        yield 'both required, extra fields preserved' => [
            [
                'email'    => ['required_at_signup' => true],
                'password' => ['required_at_signup' => true],
                'registration_fields' => ['email', 'password', 'first_name', 'last_name'],
            ],
            ['email', 'password', 'first_name', 'last_name'],
        ];

        yield 'phone-only registration' => [
            [
                'email'    => ['required_at_signup' => false],
                'password' => ['required_at_signup' => false],
                'phone'    => ['required_at_signup' => true],
                'registration_fields' => ['phone'],
            ],
            ['phone'],
        ];
    }

    // --- Placeholder guards ---

    public function testUserMethodsSkipsEmailForPlaceholderEmail(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'password' => ['enabled' => true],
            'email'    => ['enabled' => true, 'usage' => 'login', 'verification_methods' => ['otp']],
        ];
        $GLOBALS['_test_userdata'] = $this->makeUser(1, 'abc123@noreply.wsms.local');

        $methodNames = array_column($this->engine->getAvailableMethodsForUser(1), 'method');

        $this->assertContains('password', $methodNames);
        $this->assertNotContains('email_otp', $methodNames);
    }

    public function testPendingVerificationsSkipsPlaceholderEmail(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'email' => ['enabled' => true, 'verify_at_signup' => true],
        ];
        $GLOBALS['_test_userdata'] = $this->makeUser(1, 'abc123@noreply.wsms.local');
        $GLOBALS['_test_user_meta'][1] = ['wsms_email_verified' => ''];

        $pending = $this->engine->getPendingVerifications(1);

        $this->assertSame([], $pending);
    }

    private function makeUser(int $id, string $email = 'test@example.com'): object
    {
        $user = new \stdClass();
        $user->ID = $id;
        $user->user_email = $email;
        $user->user_login = 'testuser';
        $user->display_name = 'Test User';
        $user->roles = ['subscriber'];
        $user->user_registered = '2024-01-01 00:00:00';

        return $user;
    }
}
