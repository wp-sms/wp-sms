<?php

namespace WSms\Enums;

enum AuthErrorCode: string
{
    // Credential/input errors
    case InvalidCredentials = 'invalid_credentials';
    case InvalidCode = 'invalid_code';
    case InvalidToken = 'invalid_token';
    case InvalidEmail = 'invalid_email';
    case WrongPassword = 'wrong_password';

    // Channel/method errors
    case InvalidChannel = 'invalid_channel';
    case InvalidStage = 'invalid_stage';
    case MethodDisabled = 'method_disabled';
    case ChannelUnavailable = 'channel_unavailable';
    case NotEnrolled = 'not_enrolled';
    case ChallengeFailed = 'challenge_failed';

    // Account state errors
    case AccountLocked = 'account_locked';
    case AccountSuspended = 'account_suspended';
    case AccountPendingVerification = 'account_pending_verification';

    // Missing field errors
    case MissingEmail = 'missing_email';
    case MissingPassword = 'missing_password';
    case MissingPhone = 'missing_phone';
    case MissingFirstName = 'missing_first_name';
    case MissingLastName = 'missing_last_name';

    // Rate/time errors
    case RateLimited = 'rate_limited';
    case Cooldown = 'cooldown';
    case SessionExpired = 'session_expired';
    case Expired = 'expired';
    case MaxAttempts = 'max_attempts';
    case ExpiredToken = 'expired_token';
    case UsedToken = 'used_token';

    // Uniqueness/conflict errors
    case PhoneExists = 'phone_exists';
    case EmailExistsUntrusted = 'email_exists_untrusted';
    case AlreadyLinked = 'already_linked';
    case ProviderTaken = 'provider_taken';

    // Social auth errors
    case InvalidProvider = 'invalid_provider';
    case InvalidState = 'invalid_state';
    case TokenExchangeFailed = 'token_exchange_failed';
    case UserInfoFailed = 'userinfo_failed';
    case RegistrationDisabled = 'registration_disabled';
    case RegistrationFailed = 'registration_failed';

    // Other
    case CaptchaFailed = 'captcha_failed';
    case PhoneRestricted = 'phone_restricted';
    case UserNotFound = 'user_not_found';
    case NoVerification = 'no_verification';

    /**
     * Map error codes to appropriate HTTP status codes.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::InvalidCredentials,
            self::SessionExpired,
            self::InvalidToken,
            self::ExpiredToken,
            self::UsedToken => 401,

            self::AccountLocked,
            self::AccountSuspended,
            self::CaptchaFailed,
            self::AccountPendingVerification => 403,

            self::PhoneExists,
            self::EmailExistsUntrusted,
            self::AlreadyLinked,
            self::ProviderTaken => 409,

            self::RateLimited => 429,

            default => 400,
        };
    }

    /**
     * Suggest a recovery action the frontend can act on.
     */
    public function recoveryAction(): ?string
    {
        return match ($this) {
            self::InvalidCredentials,
            self::InvalidCode,
            self::WrongPassword => 'retry_input',

            self::SessionExpired,
            self::InvalidToken,
            self::InvalidStage => 'restart_flow',

            self::AccountLocked,
            self::Cooldown,
            self::RateLimited => 'wait_retry',

            self::MaxAttempts => 'request_new_code',

            self::AccountSuspended,
            self::RegistrationDisabled => 'contact_admin',

            self::NotEnrolled => 'enroll_method',

            self::MethodDisabled,
            self::ChannelUnavailable => 'choose_different_method',

            self::ChallengeFailed => 'retry_later',

            default => null,
        };
    }
}
