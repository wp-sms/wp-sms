<?php

namespace WSms\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use WSms\Enums\AuthErrorCode;

class AuthErrorCodeTest extends TestCase
{
    public function testAllValuesAreUnique(): void
    {
        $values = array_map(fn(AuthErrorCode $c) => $c->value, AuthErrorCode::cases());

        $this->assertSame(count($values), count(array_unique($values)), 'Duplicate enum values detected');
    }

    public function testHttpStatusReturnsValidCodes(): void
    {
        $validCodes = [400, 401, 403, 409, 429];

        foreach (AuthErrorCode::cases() as $case) {
            $status = $case->httpStatus();
            $this->assertContains($status, $validCodes, "{$case->name} returned unexpected HTTP status {$status}");
        }
    }

    /**
     * @dataProvider httpStatusMappingProvider
     */
    public function testHttpStatusMapping(AuthErrorCode $code, int $expectedStatus): void
    {
        $this->assertSame($expectedStatus, $code->httpStatus());
    }

    public static function httpStatusMappingProvider(): array
    {
        return [
            'invalid_credentials → 401' => [AuthErrorCode::InvalidCredentials, 401],
            'session_expired → 401'     => [AuthErrorCode::SessionExpired, 401],
            'invalid_token → 401'       => [AuthErrorCode::InvalidToken, 401],
            'expired_token → 401'       => [AuthErrorCode::ExpiredToken, 401],
            'used_token → 401'          => [AuthErrorCode::UsedToken, 401],
            'account_locked → 403'      => [AuthErrorCode::AccountLocked, 403],
            'account_suspended → 403'   => [AuthErrorCode::AccountSuspended, 403],
            'captcha_failed → 403'      => [AuthErrorCode::CaptchaFailed, 403],
            'phone_exists → 409'        => [AuthErrorCode::PhoneExists, 409],
            'already_linked → 409'      => [AuthErrorCode::AlreadyLinked, 409],
            'provider_taken → 409'      => [AuthErrorCode::ProviderTaken, 409],
            'rate_limited → 429'        => [AuthErrorCode::RateLimited, 429],
            'invalid_code → 400'        => [AuthErrorCode::InvalidCode, 400],
            'method_disabled → 400'     => [AuthErrorCode::MethodDisabled, 400],
            'missing_email → 400'       => [AuthErrorCode::MissingEmail, 400],
        ];
    }

    /**
     * @dataProvider recoveryActionProvider
     */
    public function testRecoveryAction(AuthErrorCode $code, ?string $expectedAction): void
    {
        $this->assertSame($expectedAction, $code->recoveryAction());
    }

    public static function recoveryActionProvider(): array
    {
        return [
            'invalid_credentials → retry_input'      => [AuthErrorCode::InvalidCredentials, 'retry_input'],
            'invalid_code → retry_input'              => [AuthErrorCode::InvalidCode, 'retry_input'],
            'wrong_password → retry_input'            => [AuthErrorCode::WrongPassword, 'retry_input'],
            'session_expired → restart_flow'          => [AuthErrorCode::SessionExpired, 'restart_flow'],
            'invalid_token → restart_flow'            => [AuthErrorCode::InvalidToken, 'restart_flow'],
            'account_locked → wait_retry'             => [AuthErrorCode::AccountLocked, 'wait_retry'],
            'rate_limited → wait_retry'               => [AuthErrorCode::RateLimited, 'wait_retry'],
            'max_attempts → request_new_code'         => [AuthErrorCode::MaxAttempts, 'request_new_code'],
            'account_suspended → contact_admin'       => [AuthErrorCode::AccountSuspended, 'contact_admin'],
            'not_enrolled → enroll_method'            => [AuthErrorCode::NotEnrolled, 'enroll_method'],
            'method_disabled → choose_different_method' => [AuthErrorCode::MethodDisabled, 'choose_different_method'],
            'challenge_failed → retry_later'          => [AuthErrorCode::ChallengeFailed, 'retry_later'],
            'phone_exists → null'                     => [AuthErrorCode::PhoneExists, null],
            'missing_email → null'                    => [AuthErrorCode::MissingEmail, null],
        ];
    }

    public function testFromStringRoundTrip(): void
    {
        foreach (AuthErrorCode::cases() as $case) {
            $this->assertSame($case, AuthErrorCode::from($case->value));
        }
    }

    public function testTryFromInvalidReturnsNull(): void
    {
        $this->assertNull(AuthErrorCode::tryFrom('nonexistent_error'));
    }
}
