<?php

namespace WSms\Tests\Support;

use WSms\Enums\EnrollmentTiming;

/**
 * Single source of truth for all auth setting presets.
 *
 * Every integration (and unit) test references these named presets
 * instead of inline settings arrays. Mirrors PolicyEngine::CHANNEL_DEFAULTS.
 */
class AuthScenarios
{
    private const PASSWORD_ENABLED = ['enabled' => true, 'required_at_signup' => true, 'allow_sign_in' => true];

    // ──────────────────────────────────────────────
    //  Primary auth presets
    // ──────────────────────────────────────────────

    public static function passwordOnly(): array
    {
        return [
            'password' => self::PASSWORD_ENABLED,
            'phone'    => ['enabled' => false],
            'email'    => ['enabled' => false],
        ];
    }

    public static function emailOtpOnly(): array
    {
        return [
            'password' => ['enabled' => false],
            'email'    => [
                'enabled'              => true,
                'usage'                => 'login',
                'verification_methods' => ['otp'],
                'allow_sign_in'        => true,
                'code_length'          => 6,
            ],
            'phone' => ['enabled' => false],
        ];
    }

    public static function emailMagicLinkOnly(): array
    {
        return [
            'password' => ['enabled' => false],
            'email'    => [
                'enabled'              => true,
                'usage'                => 'login',
                'verification_methods' => ['magic_link'],
                'allow_sign_in'        => true,
            ],
            'phone' => ['enabled' => false],
        ];
    }

    public static function phoneOtpOnly(): array
    {
        return [
            'password' => ['enabled' => false],
            'phone'    => [
                'enabled'              => true,
                'usage'                => 'login',
                'verification_methods' => ['otp'],
                'allow_sign_in'        => true,
                'code_length'          => 6,
            ],
            'email' => ['enabled' => false],
        ];
    }

    public static function passwordAndEmailOtp(): array
    {
        return [
            'password' => self::PASSWORD_ENABLED,
            'email'    => [
                'enabled'              => true,
                'usage'                => 'login',
                'verification_methods' => ['otp'],
                'allow_sign_in'        => true,
            ],
            'phone' => ['enabled' => false],
        ];
    }

    public static function passwordAndPhoneOtp(): array
    {
        return [
            'password' => self::PASSWORD_ENABLED,
            'phone'    => [
                'enabled'              => true,
                'usage'                => 'login',
                'verification_methods' => ['otp'],
                'allow_sign_in'        => true,
            ],
            'email' => ['enabled' => false],
        ];
    }

    public static function allChannelsEnabled(): array
    {
        return [
            'password' => self::PASSWORD_ENABLED,
            'phone'    => [
                'enabled'              => true,
                'usage'                => 'login',
                'verification_methods' => ['otp'],
                'allow_sign_in'        => true,
            ],
            'email' => [
                'enabled'              => true,
                'usage'                => 'login',
                'verification_methods' => ['otp'],
                'allow_sign_in'        => true,
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  MFA presets
    // ──────────────────────────────────────────────

    public static function mfaPhoneForAdmin(): array
    {
        return self::baseMfa('phone', ['administrator'], EnrollmentTiming::OnRegistration);
    }

    public static function mfaEmailForAll(): array
    {
        return self::baseMfa('email', ['administrator', 'editor', 'subscriber'], EnrollmentTiming::OnRegistration);
    }

    public static function mfaWithBackupCodes(): array
    {
        return array_merge(
            self::baseMfa('phone', ['administrator'], EnrollmentTiming::OnRegistration),
            ['backup_codes' => ['enabled' => true]],
        );
    }

    public static function mfaGracePeriod(int $days = 7): array
    {
        return array_merge(
            self::baseMfa('phone', ['administrator'], EnrollmentTiming::GracePeriod),
            ['grace_period_days' => $days],
        );
    }

    public static function mfaVoluntary(): array
    {
        return self::baseMfa('phone', ['administrator'], EnrollmentTiming::Voluntary);
    }

    // ──────────────────────────────────────────────
    //  Phone-only registration
    // ──────────────────────────────────────────────

    public static function phoneOnlyRegistration(): array
    {
        return [
            'password' => ['enabled' => false, 'required_at_signup' => false],
            'email'    => ['enabled' => false, 'required_at_signup' => false],
            'phone'    => [
                'enabled'              => true,
                'usage'                => 'login',
                'verification_methods' => ['otp'],
                'required_at_signup'   => true,
                'allow_sign_in'        => true,
                'code_length'          => 6,
            ],
            'registration_fields' => ['phone'],
        ];
    }

    // ──────────────────────────────────────────────
    //  Verification presets
    // ──────────────────────────────────────────────

    public static function verifyEmailAtSignup(): array
    {
        return [
            'password'            => self::PASSWORD_ENABLED,
            'email'               => [
                'enabled'              => true,
                'usage'                => 'login',
                'verification_methods' => ['otp'],
                'verify_at_signup'     => true,
                'allow_sign_in'        => true,
                'code_length'          => 6,
            ],
            'phone'               => ['enabled' => false],
            'registration_fields' => ['email', 'password'],
        ];
    }

    public static function verifyPhoneAtSignup(): array
    {
        return [
            'password'            => self::PASSWORD_ENABLED,
            'phone'               => [
                'enabled'              => true,
                'usage'                => 'login',
                'verification_methods' => ['otp'],
                'required_at_signup'   => true,
                'verify_at_signup'     => true,
                'allow_sign_in'        => true,
                'code_length'          => 6,
            ],
            'email'               => ['enabled' => false],
            'registration_fields' => ['email', 'password'],
        ];
    }

    public static function verifyBothAtSignup(): array
    {
        return [
            'password'            => self::PASSWORD_ENABLED,
            'email'               => [
                'enabled'              => true,
                'usage'                => 'login',
                'verification_methods' => ['otp'],
                'verify_at_signup'     => true,
                'allow_sign_in'        => true,
                'code_length'          => 6,
            ],
            'phone'               => [
                'enabled'              => true,
                'usage'                => 'login',
                'verification_methods' => ['otp'],
                'required_at_signup'   => true,
                'verify_at_signup'     => true,
                'allow_sign_in'        => true,
                'code_length'          => 6,
            ],
            'registration_fields' => ['email', 'password'],
        ];
    }

    public static function verifyAtLogin(): array
    {
        return [
            'password' => self::PASSWORD_ENABLED,
            'email'    => [
                'enabled'          => true,
                'usage'            => 'login',
                'verify_at_signup' => true,
                'allow_sign_in'    => true,
            ],
            'phone' => ['enabled' => false],
        ];
    }

    // ──────────────────────────────────────────────
    //  Combinators
    // ──────────────────────────────────────────────

    /**
     * Deep merge overrides into a base preset.
     */
    public static function withOverrides(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = array_merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    // ──────────────────────────────────────────────
    //  Exhaustive lists (for data providers + canary)
    // ──────────────────────────────────────────────

    /**
     * @return string[]
     */
    public static function allChannelIds(): array
    {
        return ['password', 'email', 'phone', 'backup_codes'];
    }

    /**
     * @return string[]
     */
    public static function verificationMethods(): array
    {
        return ['otp', 'magic_link'];
    }

    /**
     * All named scenario presets, keyed by name.
     *
     * @return array<string, array>
     */
    public static function allPresets(): array
    {
        return [
            'phoneOnlyRegistration' => self::phoneOnlyRegistration(),
            'passwordOnly'        => self::passwordOnly(),
            'emailOtpOnly'        => self::emailOtpOnly(),
            'emailMagicLinkOnly'  => self::emailMagicLinkOnly(),
            'phoneOtpOnly'        => self::phoneOtpOnly(),
            'passwordAndEmailOtp' => self::passwordAndEmailOtp(),
            'passwordAndPhoneOtp' => self::passwordAndPhoneOtp(),
            'allChannelsEnabled'  => self::allChannelsEnabled(),
            'mfaPhoneForAdmin'    => self::mfaPhoneForAdmin(),
            'mfaEmailForAll'      => self::mfaEmailForAll(),
            'mfaWithBackupCodes'  => self::mfaWithBackupCodes(),
            'mfaGracePeriod'      => self::mfaGracePeriod(),
            'mfaVoluntary'        => self::mfaVoluntary(),
            'verifyEmailAtSignup' => self::verifyEmailAtSignup(),
            'verifyPhoneAtSignup' => self::verifyPhoneAtSignup(),
            'verifyBothAtSignup'  => self::verifyBothAtSignup(),
            'verifyAtLogin'       => self::verifyAtLogin(),
        ];
    }

    // ──────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────

    private static function baseMfa(string $channel, array $roles, EnrollmentTiming $timing): array
    {
        $channels = [
            'password' => self::PASSWORD_ENABLED,
            'phone'    => ['enabled' => false],
            'email'    => ['enabled' => false],
        ];

        $channels[$channel] = ['enabled' => true, 'usage' => 'mfa'];

        return array_merge($channels, [
            'mfa_required_roles' => $roles,
            'enrollment_timing'  => $timing->value,
        ]);
    }
}
