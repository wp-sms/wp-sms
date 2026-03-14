import type { AuthSettings } from './types';

const PASSWORD_ENABLED = {
  enabled: true,
  required_at_signup: true,
  allow_sign_in: true,
} as const;

// ──────────────────────────────────────────────
//  Primary auth presets
// ──────────────────────────────────────────────

export function passwordOnly(): AuthSettings {
  return {
    password: { ...PASSWORD_ENABLED },
    phone: { enabled: false },
    email: { enabled: false },
  };
}

export function emailOtpOnly(): AuthSettings {
  return {
    password: { enabled: false },
    email: {
      enabled: true,
      usage: 'login',
      verification_methods: ['otp'],
      allow_sign_in: true,
      code_length: 6,
    },
    phone: { enabled: false },
  };
}

export function emailMagicLinkOnly(): AuthSettings {
  return {
    password: { enabled: false },
    email: {
      enabled: true,
      usage: 'login',
      verification_methods: ['magic_link'],
      allow_sign_in: true,
    },
    phone: { enabled: false },
  };
}

export function phoneOtpOnly(): AuthSettings {
  return {
    password: { enabled: false },
    phone: {
      enabled: true,
      usage: 'login',
      verification_methods: ['otp'],
      allow_sign_in: true,
      code_length: 6,
    },
    email: { enabled: false },
  };
}

export function passwordAndEmailOtp(): AuthSettings {
  return {
    password: { ...PASSWORD_ENABLED },
    email: {
      enabled: true,
      usage: 'login',
      verification_methods: ['otp'],
      allow_sign_in: true,
    },
    phone: { enabled: false },
  };
}

export function passwordAndPhoneOtp(): AuthSettings {
  return {
    password: { ...PASSWORD_ENABLED },
    phone: {
      enabled: true,
      usage: 'login',
      verification_methods: ['otp'],
      allow_sign_in: true,
    },
    email: { enabled: false },
  };
}

export function allChannelsEnabled(): AuthSettings {
  return {
    password: { ...PASSWORD_ENABLED },
    phone: {
      enabled: true,
      usage: 'login',
      verification_methods: ['otp'],
      allow_sign_in: true,
    },
    email: {
      enabled: true,
      usage: 'login',
      verification_methods: ['otp'],
      allow_sign_in: true,
    },
  };
}

// ──────────────────────────────────────────────
//  MFA presets
// ──────────────────────────────────────────────

function baseMfa(
  channel: 'phone' | 'email',
  roles: string[],
  timing: 'on_registration' | 'grace_period' | 'voluntary',
): AuthSettings {
  const channels: AuthSettings = {
    password: { ...PASSWORD_ENABLED },
    phone: { enabled: false },
    email: { enabled: false },
  };

  channels[channel] = { enabled: true, usage: 'mfa' };

  return {
    ...channels,
    mfa_required_roles: roles,
    enrollment_timing: timing,
  };
}

export function mfaPhoneForAdmin(): AuthSettings {
  return baseMfa('phone', ['administrator'], 'on_registration');
}

export function mfaEmailForAll(): AuthSettings {
  return baseMfa('email', ['administrator', 'editor', 'subscriber'], 'on_registration');
}

export function mfaWithBackupCodes(): AuthSettings {
  return {
    ...baseMfa('phone', ['administrator'], 'on_registration'),
    backup_codes: { enabled: true },
  };
}

export function mfaGracePeriod(days = 7): AuthSettings {
  return {
    ...baseMfa('phone', ['administrator'], 'grace_period'),
    grace_period_days: days,
  };
}

export function mfaVoluntary(): AuthSettings {
  return baseMfa('phone', ['administrator'], 'voluntary');
}

export function mfaTotpForAdmin(): AuthSettings {
  return {
    password: { ...PASSWORD_ENABLED },
    phone: { enabled: false },
    email: { enabled: false },
    totp: { enabled: true },
    mfa_required_roles: ['administrator'],
    enrollment_timing: 'on_registration',
  };
}

// ──────────────────────────────────────────────
//  Verification presets
// ──────────────────────────────────────────────

export function verifyEmailAtSignup(): AuthSettings {
  return {
    password: { ...PASSWORD_ENABLED },
    email: {
      enabled: true,
      usage: 'login',
      verification_methods: ['otp'],
      verify_at_signup: true,
      allow_sign_in: true,
      code_length: 6,
    },
    phone: { enabled: false },
    registration_fields: ['email', 'password'],
  };
}

export function verifyPhoneAtSignup(): AuthSettings {
  return {
    password: { ...PASSWORD_ENABLED },
    phone: {
      enabled: true,
      usage: 'login',
      verification_methods: ['otp'],
      required_at_signup: true,
      verify_at_signup: true,
      allow_sign_in: true,
      code_length: 6,
    },
    email: { enabled: false },
    registration_fields: ['email', 'password'],
  };
}

export function verifyBothAtSignup(): AuthSettings {
  return {
    password: { ...PASSWORD_ENABLED },
    email: {
      enabled: true,
      usage: 'login',
      verification_methods: ['otp'],
      verify_at_signup: true,
      allow_sign_in: true,
      code_length: 6,
    },
    phone: {
      enabled: true,
      usage: 'login',
      verification_methods: ['otp'],
      required_at_signup: true,
      verify_at_signup: true,
      allow_sign_in: true,
      code_length: 6,
    },
    registration_fields: ['email', 'password'],
  };
}

export function verifyAtLogin(): AuthSettings {
  return {
    password: { ...PASSWORD_ENABLED },
    email: {
      enabled: true,
      usage: 'login',
      verify_at_signup: true,
      allow_sign_in: true,
    },
    phone: { enabled: false },
  };
}

// ──────────────────────────────────────────────
//  Social login presets
// ──────────────────────────────────────────────

export function telegramSocialLogin(): AuthSettings {
  return {
    password: { ...PASSWORD_ENABLED },
    phone: { enabled: false },
    email: { enabled: false },
    social: {
      telegram: {
        enabled: true,
        client_id: 'test-telegram-client-id',
        client_secret: 'test-telegram-client-secret',
      },
    },
  };
}

export function telegramSocialWithMfa(): AuthSettings {
  return {
    password: { ...PASSWORD_ENABLED },
    phone: { enabled: false },
    email: { enabled: false },
    social: {
      telegram: {
        enabled: true,
        client_id: 'test-telegram-client-id',
        client_secret: 'test-telegram-client-secret',
      },
    },
    telegram: {
      bot_token: '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
      bot_username: 'test_bot',
      webhook_secret: 'test-webhook-secret',
      enabled: true,
      code_length: 6,
      expiry: 300,
      max_attempts: 3,
      cooldown: 60,
    },
  };
}

export function googleAndTelegramSocial(): AuthSettings {
  return {
    password: { ...PASSWORD_ENABLED },
    phone: { enabled: false },
    email: { enabled: false },
    social: {
      google: {
        enabled: true,
        client_id: 'test-google-client-id',
        client_secret: 'test-google-client-secret',
      },
      telegram: {
        enabled: true,
        client_id: 'test-telegram-client-id',
        client_secret: 'test-telegram-client-secret',
      },
    },
  };
}

// ──────────────────────────────────────────────
//  Exhaustive lists
// ──────────────────────────────────────────────

export const allPresets = {
  passwordOnly,
  emailOtpOnly,
  emailMagicLinkOnly,
  phoneOtpOnly,
  passwordAndEmailOtp,
  passwordAndPhoneOtp,
  allChannelsEnabled,
  mfaPhoneForAdmin,
  mfaEmailForAll,
  mfaWithBackupCodes,
  mfaGracePeriod,
  mfaVoluntary,
  mfaTotpForAdmin,
  verifyEmailAtSignup,
  verifyPhoneAtSignup,
  verifyBothAtSignup,
  verifyAtLogin,
  telegramSocialLogin,
  telegramSocialWithMfa,
  googleAndTelegramSocial,
} as const;

export const primaryPresets = [
  ['passwordOnly', passwordOnly()],
  ['emailOtpOnly', emailOtpOnly()],
  ['emailMagicLinkOnly', emailMagicLinkOnly()],
  ['phoneOtpOnly', phoneOtpOnly()],
  ['passwordAndEmailOtp', passwordAndEmailOtp()],
  ['passwordAndPhoneOtp', passwordAndPhoneOtp()],
  ['allChannelsEnabled', allChannelsEnabled()],
] as const;

export const verificationPresets = [
  ['verifyEmailAtSignup', verifyEmailAtSignup()],
  ['verifyPhoneAtSignup', verifyPhoneAtSignup()],
  ['verifyBothAtSignup', verifyBothAtSignup()],
  ['verifyAtLogin', verifyAtLogin()],
] as const;

export const mfaPresets = [
  ['mfaPhoneForAdmin', mfaPhoneForAdmin()],
  ['mfaEmailForAll', mfaEmailForAll()],
  ['mfaWithBackupCodes', mfaWithBackupCodes()],
  ['mfaGracePeriod', mfaGracePeriod()],
  ['mfaVoluntary', mfaVoluntary()],
  ['mfaTotpForAdmin', mfaTotpForAdmin()],
] as const;

export const socialPresets = [
  ['telegramSocialLogin', telegramSocialLogin()],
  ['telegramSocialWithMfa', telegramSocialWithMfa()],
  ['googleAndTelegramSocial', googleAndTelegramSocial()],
] as const;
