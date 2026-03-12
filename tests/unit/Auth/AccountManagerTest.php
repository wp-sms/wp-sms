<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Auth\AccountManager;
use WSms\Auth\AuthSession;
use WSms\Mfa\MfaManager;
use WSms\Mfa\OtpGenerator;

class AccountManagerTest extends TestCase
{
    private AccountManager $manager;
    private MockObject&AuditLogger $auditLogger;
    private MockObject&OtpGenerator $otpGenerator;
    private MockObject&MfaManager $mfaManager;
    private MockObject&AuthSession $authSession;

    protected function setUp(): void
    {
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->otpGenerator = $this->createMock(OtpGenerator::class);
        $this->mfaManager = $this->createMock(MfaManager::class);
        $this->authSession = $this->createMock(AuthSession::class);

        $this->manager = new AccountManager(
            $this->auditLogger,
            $this->otpGenerator,
            $this->mfaManager,
            $this->authSession,
        );

        $this->otpGenerator->method('generateToken')->willReturn('test-token-abc');
        $this->otpGenerator->method('hash')->willReturn('hashed-token-abc');
        $this->authSession->method('create')->willReturn('reg-session-token');

        // Stub $wpdb.
        $wpdb = new \stdClass();
        $wpdb->prefix = 'wp_';
        $wpdb->insert_id = 1;
        $GLOBALS['wpdb'] = $wpdb;

        // Allow insert to succeed by default.
        $wpdb->insert = function () {
            return true;
        };

        unset(
            $GLOBALS['_test_wp_insert_user_result'],
            $GLOBALS['_test_wp_check_password_result'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_get_user_by_result'],
            $GLOBALS['_test_current_user_id'],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['wpdb'],
            $GLOBALS['_test_wp_insert_user_result'],
            $GLOBALS['_test_wp_check_password_result'],
            $GLOBALS['_test_userdata'],
            $GLOBALS['_test_get_user_by_result'],
            $GLOBALS['_test_current_user_id'],
        );
    }

    // --- registerUser ---

    public function testRegisterUserSucceeds(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 42;
        $this->stubWpdb();

        $this->auditLogger->expects($this->once())->method('log');

        $result = $this->manager->registerUser([
            'email'    => 'new@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(42, $result['user_id']);
        $this->assertSame('Registration successful.', $result['message']);
    }

    public function testRegisterUserRequiresMfaOnRegistration(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 10;
        $this->stubWpdb();

        // Simulate on_registration enrollment timing.
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password'],
            'enrollment_timing'   => 'on_registration',
        ];

        // Override get_option to use test options.
        $result = $this->manager->registerUser([
            'email'    => 'mfa@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertTrue($result['success']);
    }

    public function testRegisterUserFailsWithDuplicateEmail(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = new \WP_Error('existing_user_email', 'Email already exists.');
        $this->stubWpdb();

        $result = $this->manager->registerUser([
            'email'    => 'taken@example.com',
            'password' => 'Pass123!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('existing_user_email', $result['error']);
    }

    public function testRegisterUserFailsWhenPhoneRequiredButMissing(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password'],
            'phone' => ['required_at_signup' => true],
        ];

        $result = $this->manager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_phone', $result['error']);
    }

    public function testRegisterUserSucceedsWhenPhoneRequiredAndProvided(): void
    {
        $GLOBALS['_test_wp_insert_user_result'] = 50;
        $this->stubWpdb();

        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password'],
            'phone' => ['required_at_signup' => true],
        ];

        $result = $this->manager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'StrongPass1!',
            'phone'    => '+1234567890',
        ]);

        $this->assertTrue($result['success']);
    }

    public function testRegisterUserFailsWhenFirstNameRequired(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'registration_fields' => ['email', 'password', 'first_name'],
        ];

        $result = $this->manager->registerUser([
            'email'    => 'test@example.com',
            'password' => 'StrongPass1!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_first_name', $result['error']);
    }

    public function testRegisterUserFailsWithMissingEmail(): void
    {
        $result = $this->manager->registerUser([
            'password' => 'Pass123!',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_email', $result['error']);
    }

    public function testRegisterUserFailsWithMissingPassword(): void
    {
        $result = $this->manager->registerUser([
            'email' => 'test@example.com',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_password', $result['error']);
    }

    // --- initiatePasswordReset ---

    public function testInitiatePasswordResetCreatesVerification(): void
    {
        $user = $this->makeUser(5);
        $GLOBALS['_test_get_user_by_result'] = $user;
        $this->stubWpdb();

        $this->auditLogger->expects($this->once())->method('log');

        // Should not throw.
        $this->manager->initiatePasswordReset('user@example.com');
        $this->addToAssertionCount(1);
    }

    public function testInitiatePasswordResetSilentlyIgnoresUnknownEmail(): void
    {
        $GLOBALS['_test_get_user_by_result'] = false;

        $this->auditLogger->expects($this->never())->method('log');

        // Should not throw.
        $this->manager->initiatePasswordReset('nobody@example.com');
        $this->addToAssertionCount(1);
    }

    // --- completePasswordReset ---

    public function testCompletePasswordResetSucceeds(): void
    {
        $verification = $this->makeVerification(1, 'password_reset');
        $this->stubWpdbLookup($verification);

        $this->auditLogger->expects($this->once())->method('log');

        $result = $this->manager->completePasswordReset('test-token-abc', 'NewPass1!');

        $this->assertTrue($result['success']);
        $this->assertSame('Password has been reset successfully.', $result['message']);
    }

    public function testCompletePasswordResetFailsWithExpiredToken(): void
    {
        $verification = $this->makeVerification(1, 'password_reset', expired: true);
        $this->stubWpdbLookup($verification);

        $result = $this->manager->completePasswordReset('test-token-abc', 'NewPass1!');

        $this->assertFalse($result['success']);
        $this->assertSame('expired_token', $result['error']);
    }

    public function testCompletePasswordResetFailsWithUsedToken(): void
    {
        $verification = $this->makeVerification(1, 'password_reset', used: true);
        $this->stubWpdbLookup($verification);

        $result = $this->manager->completePasswordReset('test-token-abc', 'NewPass1!');

        $this->assertFalse($result['success']);
        $this->assertSame('used_token', $result['error']);
    }

    public function testCompletePasswordResetFailsWithInvalidToken(): void
    {
        $this->stubWpdbLookup(null);

        $result = $this->manager->completePasswordReset('bad-token', 'NewPass1!');

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_token', $result['error']);
    }

    // --- verifyEmail ---

    public function testVerifyEmailSucceeds(): void
    {
        $verification = $this->makeVerification(3, 'email_verify');
        $this->stubWpdbLookup($verification);

        $this->auditLogger->expects($this->once())->method('log');

        $result = $this->manager->verifyEmail('test-token-abc');

        $this->assertTrue($result['success']);
        $this->assertSame('Email verified successfully.', $result['message']);
    }

    // --- updateProfile ---

    public function testUpdateProfileDisplayName(): void
    {
        $result = $this->manager->updateProfile(1, ['display_name' => 'New Name']);

        $this->assertTrue($result['success']);
    }

    public function testUpdateProfilePhoneTriggersVerification(): void
    {
        $result = $this->manager->updateProfile(1, ['phone' => '+1234567890']);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['phone_verification_required']);
    }

    public function testUpdateProfileEmailTriggersVerification(): void
    {
        $this->stubWpdb();

        $result = $this->manager->updateProfile(1, ['email' => 'new@example.com']);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['email_verification_required']);
    }

    public function testUpdateProfileRejectsInvalidEmail(): void
    {
        $result = $this->manager->updateProfile(1, ['email' => 'not-an-email']);

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_email', $result['error']);
    }

    // --- changePassword ---

    public function testChangePasswordSucceeds(): void
    {
        $user = $this->makeUser(1);
        $user->user_pass = 'hashed-pass';
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_wp_check_password_result'] = true;

        $this->auditLogger->expects($this->once())->method('log');

        $result = $this->manager->changePassword(1, 'oldPass', 'newPass');

        $this->assertTrue($result['success']);
        $this->assertSame('Password changed successfully.', $result['message']);
    }

    public function testChangePasswordFailsWithWrongPassword(): void
    {
        $user = $this->makeUser(1);
        $user->user_pass = 'hashed-pass';
        $GLOBALS['_test_userdata'] = $user;
        $GLOBALS['_test_wp_check_password_result'] = false;

        $result = $this->manager->changePassword(1, 'wrongPass', 'newPass');

        $this->assertFalse($result['success']);
        $this->assertSame('wrong_password', $result['error']);
    }

    // --- logout ---

    public function testLogoutLogsEventAndCallsWpLogout(): void
    {
        $GLOBALS['_test_current_user_id'] = 5;

        $this->auditLogger->expects($this->once())->method('log');

        $this->manager->logout();

        $this->assertSame(0, $GLOBALS['_test_current_user_id']);
    }

    // --- Helpers ---

    private function makeUser(int $id): object
    {
        $user = new \stdClass();
        $user->ID = $id;
        $user->user_email = 'test@example.com';
        $user->user_login = 'testuser';
        $user->display_name = 'Test User';
        $user->roles = ['subscriber'];
        $user->user_pass = '';

        return $user;
    }

    private function makeVerification(int $userId, string $type, bool $expired = false, bool $used = false): object
    {
        $v = new \stdClass();
        $v->id = 1;
        $v->user_id = $userId;
        $v->type = $type;
        $v->identifier = 'test@example.com';
        $v->code = 'hashed-token-abc';
        $v->expires_at = $expired
            ? gmdate('Y-m-d H:i:s', time() - 3600)
            : gmdate('Y-m-d H:i:s', time() + 3600);
        $v->used_at = $used ? gmdate('Y-m-d H:i:s') : null;

        return $v;
    }

    private function stubWpdb(): void
    {
        $wpdb = $GLOBALS['wpdb'];

        // Make insert callable as method.
        if (!method_exists($wpdb, 'insert')) {
            $GLOBALS['wpdb'] = new class {
                public string $prefix = 'wp_';
                public int $insert_id = 1;

                public function insert($table, $data, $format = null): bool
                {
                    return true;
                }

                public function prepare(string $query, ...$args): string
                {
                    return $query;
                }

                public function get_row($query): ?object
                {
                    return null;
                }

                public function update($table, $data, $where, $format = null, $whereFormat = null): bool
                {
                    return true;
                }
            };
        }
    }

    private function stubWpdbLookup(?object $result): void
    {
        $GLOBALS['wpdb'] = new class($result) {
            public string $prefix = 'wp_';
            private ?object $lookupResult;

            public function __construct(?object $lookupResult)
            {
                $this->lookupResult = $lookupResult;
            }

            public function insert($table, $data, $format = null): bool
            {
                return true;
            }

            public function prepare(string $query, ...$args): string
            {
                return $query;
            }

            public function get_row($query): ?object
            {
                return $this->lookupResult;
            }

            public function update($table, $data, $where, $format = null, $whereFormat = null): bool
            {
                return true;
            }
        };
    }
}
