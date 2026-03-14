<?php

namespace WSms\Enums;

enum SessionStage: string
{
    case ChallengePending = 'challenge_pending';
    case PrimaryVerified = 'primary_verified';
    case MfaPending = 'mfa_pending';
    case RegistrationVerify = 'registration_verify';
    case VerificationPending = 'verification_pending';

    /**
     * Explicit valid stage transitions.
     */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::ChallengePending => $next === self::PrimaryVerified,
            self::PrimaryVerified => in_array($next, [self::MfaPending, self::VerificationPending], true),
            self::MfaPending => false,
            self::RegistrationVerify => false,
            self::VerificationPending => false,
        };
    }
}
