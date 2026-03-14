<?php

namespace WSms\Tests\Unit\Verification;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Mfa\OtpGenerator;
use WSms\Verification\VerificationConfig;
use WSms\Verification\VerificationResult;
use WSms\Verification\VerificationService;
use WSms\Verification\VerificationSession;

class VerificationServiceTest extends TestCase
{
    private VerificationService $service;
    private VerificationSession $session;
    private MockObject&AuditLogger $auditLogger;
    private OtpGenerator $otpGenerator;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_transients'] = [];
        $GLOBALS['_test_do_action_calls'] = [];
        $GLOBALS['_test_has_action'] = ['wsms_send_sms' => true];
        $GLOBALS['_test_user_meta'] = [];
        $GLOBALS['wpdb'] = new \WSms\Tests\Support\WpdbFake();

        $config = new VerificationConfig();
        $this->otpGenerator = new OtpGenerator();
        $this->session = new VerificationSession($config);
        $this->auditLogger = $this->createMock(AuditLogger::class);

        $this->service = new VerificationService(
            $this->otpGenerator,
            $this->session,
            $this->auditLogger,
            $config,
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_transients'] = [];
        $GLOBALS['_test_do_action_calls'] = [];
        $GLOBALS['_test_has_action'] = [];
        $GLOBALS['_test_user_meta'] = [];
        unset($GLOBALS['wpdb']);
    }

    public function testSendCodeEmailSuccess(): void
    {
        $result = $this->service->sendCode('email', 'test@example.com');

        $this->assertTrue($result->success);
        $this->assertNotNull($result->sessionToken);
        $this->assertNotNull($result->maskedIdentifier);
        $this->assertNotNull($result->expiresIn);
        $this->assertSame('Verification code sent.', $result->message);

        // Verify OTP was inserted.
        $wpdb = $GLOBALS['wpdb'];
        $this->assertCount(1, $wpdb->inserts);
        $insert = $wpdb->inserts[0]['data'];
        $this->assertSame('standalone_email', $insert['type']);
        $this->assertSame('test@example.com', $insert['identifier']);
        $this->assertNull($insert['user_id']);
    }

    public function testSendCodePhoneSuccess(): void
    {
        $result = $this->service->sendCode('phone', '+1234567890');

        $this->assertTrue($result->success);
        $this->assertNotNull($result->sessionToken);
    }

    public function testSendCodeFailsWhenChannelDisabled(): void
    {
        $GLOBALS['_test_options']['wsms_verification_settings'] = [
            'email' => ['enabled' => false],
        ];

        $service = $this->createServiceWithFreshConfig();
        $result = $service->sendCode('email', 'test@example.com');

        $this->assertFalse($result->success);
        $this->assertSame('channel_disabled', $result->error);
    }

    public function testSendCodeFailsWithEmptyEmail(): void
    {
        $result = $this->service->sendCode('email', '');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_identifier', $result->error);
    }

    public function testSendCodeFailsWhenNoSmsGateway(): void
    {
        $GLOBALS['_test_has_action'] = []; // No SMS handler registered.

        $result = $this->service->sendCode('phone', '+1234567890');

        $this->assertFalse($result->success);
        $this->assertSame('no_sms_gateway', $result->error);
    }

    public function testSendCodeReusesExistingSession(): void
    {
        $result1 = $this->service->sendCode('email', 'test@example.com');
        $token = $result1->sessionToken;

        // Wait for cooldown workaround: clear the verification to avoid cooldown.
        $GLOBALS['wpdb']->reset();

        $result2 = $this->service->sendCode('email', 'test@example.com', $token);

        $this->assertTrue($result2->success);
        $this->assertSame($token, $result2->sessionToken);
    }

    public function testSendCodeCreatesNewSessionWhenTokenInvalid(): void
    {
        $result = $this->service->sendCode('email', 'test@example.com', 'invalid-token');

        $this->assertTrue($result->success);
        $this->assertNotSame('invalid-token', $result->sessionToken);
    }

    public function testSendCodeEmailNormalizesCase(): void
    {
        $result = $this->service->sendCode('email', 'Test@EXAMPLE.com');

        $this->assertTrue($result->success);

        $wpdb = $GLOBALS['wpdb'];
        $insert = $wpdb->inserts[0]['data'];
        $this->assertSame('test@example.com', $insert['identifier']);
    }

    public function testSendCodeFiresOtpGeneratedAction(): void
    {
        $this->service->sendCode('email', 'test@example.com');

        $otpActions = array_filter(
            $GLOBALS['_test_do_action_calls'],
            fn($call) => $call['hook'] === 'wsms_otp_generated',
        );

        $this->assertNotEmpty($otpActions);
    }

    public function testSendCodeWithUserId(): void
    {
        $result = $this->service->sendCode('email', 'test@example.com', null, 42);

        $this->assertTrue($result->success);

        $wpdb = $GLOBALS['wpdb'];
        $insert = $wpdb->inserts[0]['data'];
        $this->assertSame(42, $insert['user_id']);
    }

    public function testVerifyCodeSuccess(): void
    {
        // Send code first.
        $sendResult = $this->service->sendCode('email', 'test@example.com');
        $token = $sendResult->sessionToken;

        // Extract the OTP from the action calls.
        $otp = $this->extractOtpFromActions();

        $result = $this->service->verifyCode('email', 'test@example.com', $otp, $token);

        $this->assertTrue($result->success);
        $this->assertSame('Verification successful.', $result->message);
    }

    public function testVerifyCodeFailsWithInvalidSession(): void
    {
        $result = $this->service->verifyCode('email', 'test@example.com', '123456', 'bad-token');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_session', $result->error);
    }

    public function testVerifyCodeFailsWithWrongCode(): void
    {
        $sendResult = $this->service->sendCode('email', 'test@example.com');

        $result = $this->service->verifyCode('email', 'test@example.com', '000000', $sendResult->sessionToken);

        $this->assertFalse($result->success);
        $this->assertSame('invalid_code', $result->error);
    }

    public function testVerifyCodeFailsWhenExpired(): void
    {
        $sendResult = $this->service->sendCode('email', 'test@example.com');

        // Expire the verification record.
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->expireVerification(1);

        $otp = $this->extractOtpFromActions();
        $result = $this->service->verifyCode('email', 'test@example.com', $otp, $sendResult->sessionToken);

        $this->assertFalse($result->success);
        $this->assertSame('expired', $result->error);
    }

    public function testVerifyCodeFailsWhenMaxAttempts(): void
    {
        $sendResult = $this->service->sendCode('email', 'test@example.com');

        // Exhaust attempts.
        $wpdb = $GLOBALS['wpdb'];
        $wpdb->exhaustVerificationAttempts(1);

        $otp = $this->extractOtpFromActions();
        $result = $this->service->verifyCode('email', 'test@example.com', $otp, $sendResult->sessionToken);

        $this->assertFalse($result->success);
        $this->assertSame('max_attempts', $result->error);
    }

    public function testVerifyCodeFailsWithDifferentIdentifier(): void
    {
        $sendResult = $this->service->sendCode('email', 'test@example.com');
        $otp = $this->extractOtpFromActions();

        // Try to verify with a different email.
        $result = $this->service->verifyCode('email', 'other@example.com', $otp, $sendResult->sessionToken);

        $this->assertFalse($result->success);
        $this->assertSame('no_verification', $result->error);
    }

    public function testVerifyCodeUpdatesUserMetaWhenUserIdProvided(): void
    {
        $sendResult = $this->service->sendCode('email', 'test@example.com', null, 42);
        $otp = $this->extractOtpFromActions();

        $this->service->verifyCode('email', 'test@example.com', $otp, $sendResult->sessionToken, 42);

        $this->assertSame('1', $GLOBALS['_test_user_meta'][42]['wsms_email_verified']);
    }

    public function testVerifyCodeFiresIdentifierVerifiedAction(): void
    {
        $sendResult = $this->service->sendCode('email', 'test@example.com');
        $otp = $this->extractOtpFromActions();

        $this->service->verifyCode('email', 'test@example.com', $otp, $sendResult->sessionToken);

        $verified = array_filter(
            $GLOBALS['_test_do_action_calls'],
            fn($call) => $call['hook'] === 'wsms_identifier_verified',
        );

        $this->assertNotEmpty($verified);
    }

    public function testIsVerifiedReturnsTrueAfterVerification(): void
    {
        $sendResult = $this->service->sendCode('email', 'test@example.com');
        $otp = $this->extractOtpFromActions();

        $this->assertFalse($this->service->isVerified('email', 'test@example.com', $sendResult->sessionToken));

        $this->service->verifyCode('email', 'test@example.com', $otp, $sendResult->sessionToken);

        $this->assertTrue($this->service->isVerified('email', 'test@example.com', $sendResult->sessionToken));
    }

    public function testIsVerifiedReturnsFalseForInvalidToken(): void
    {
        $this->assertFalse($this->service->isVerified('email', 'test@example.com', 'bad-token'));
    }

    public function testEmailMaskingUsesEmailMasker(): void
    {
        $result = $this->service->sendCode('email', 'john@example.com');

        // EmailMasker: j***@example.com
        $this->assertSame('j***@example.com', $result->maskedIdentifier);
    }

    public function testPhoneMaskingUsesPhoneMasker(): void
    {
        $result = $this->service->sendCode('phone', '+1234567890');

        // PhoneMasker: +12*****7890
        $this->assertSame('+12****7890', $result->maskedIdentifier);
    }

    public function testPhoneAcceptsLocalFormat(): void
    {
        $result = $this->service->sendCode('phone', '09123456789');

        $this->assertTrue($result->success);
    }

    public function testPhoneRejectsInvalidFormat(): void
    {
        $result = $this->service->sendCode('phone', '123');

        $this->assertFalse($result->success);
        $this->assertSame('invalid_identifier', $result->error);
    }

    public function testVerificationResultToArray(): void
    {
        $result = VerificationResult::codeSent('token123', 'j***@example.com', 300);
        $array = $result->toArray();

        $this->assertTrue($array['success']);
        $this->assertSame('token123', $array['session_token']);
        $this->assertSame('j***@example.com', $array['masked_identifier']);
        $this->assertSame(300, $array['expires_in']);
        $this->assertArrayNotHasKey('error', $array);
    }

    public function testVerificationResultFailedToArray(): void
    {
        $result = VerificationResult::failed('invalid_code', 'Wrong code.');
        $array = $result->toArray();

        $this->assertFalse($array['success']);
        $this->assertSame('invalid_code', $array['error']);
        $this->assertArrayNotHasKey('session_token', $array);
    }

    public function testSendCodeCooldownBlocksResend(): void
    {
        $result1 = $this->service->sendCode('email', 'test@example.com');
        $this->assertTrue($result1->success);

        // Second send immediately with same session — should hit cooldown.
        $result2 = $this->service->sendCode('email', 'test@example.com', $result1->sessionToken);
        $this->assertFalse($result2->success);
        $this->assertSame('cooldown', $result2->error);
        $this->assertNotNull($result2->retryAfter);
    }

    public function testSendCodeRateLimitsPerIdentifier(): void
    {
        // Send 3 times (IDENTIFIER_RATE_LIMIT_MAX = 3).
        // Each call needs a fresh session (no cooldown) but same identifier transient.
        for ($i = 0; $i < 3; $i++) {
            $GLOBALS['wpdb']->reset();
            $GLOBALS['_test_do_action_calls'] = [];
            $result = $this->service->sendCode('email', 'test@example.com');
            $this->assertTrue($result->success, "Send #{$i} should succeed");
        }

        // 4th send — per-identifier limit reached.
        $GLOBALS['wpdb']->reset();
        $result = $this->service->sendCode('email', 'test@example.com');
        $this->assertFalse($result->success);
        $this->assertSame('rate_limited', $result->error);
        $this->assertNotNull($result->retryAfter);
    }

    public function testSendCodeRateLimitAllowsDifferentIdentifier(): void
    {
        // Exhaust rate limit for test@example.com.
        for ($i = 0; $i < 3; $i++) {
            $GLOBALS['wpdb']->reset();
            $this->service->sendCode('email', 'test@example.com');
        }

        // Different identifier should still work.
        $GLOBALS['wpdb']->reset();
        $result = $this->service->sendCode('email', 'other@example.com');
        $this->assertTrue($result->success);
    }

    public function testVerifyCodeFailsWhenAlreadyUsed(): void
    {
        $sendResult = $this->service->sendCode('email', 'test@example.com');
        $otp = $this->extractOtpFromActions();

        // First verify succeeds.
        $result1 = $this->service->verifyCode('email', 'test@example.com', $otp, $sendResult->sessionToken);
        $this->assertTrue($result1->success);

        // Second verify with same code — already used.
        $result2 = $this->service->verifyCode('email', 'test@example.com', $otp, $sendResult->sessionToken);
        $this->assertFalse($result2->success);
    }

    private function extractOtpFromActions(): string
    {
        foreach ($GLOBALS['_test_do_action_calls'] as $call) {
            if ($call['hook'] === 'wsms_otp_generated') {
                return $call['args'][1]; // $otp is second argument.
            }
        }

        $this->fail('wsms_otp_generated action was not fired.');
    }

    private function createServiceWithFreshConfig(): VerificationService
    {
        $config = new VerificationConfig();

        return new VerificationService(
            $this->otpGenerator,
            new VerificationSession($config),
            $this->auditLogger,
            $config,
        );
    }
}
