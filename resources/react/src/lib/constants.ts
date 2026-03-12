import type { LucideIcon } from 'lucide-react';
import { KeyRound, Smartphone, Mail, Link2, KeySquare } from 'lucide-react';
import type { AuthSettings } from './api';

export const PRIMARY_METHODS: readonly { id: string; label: string; description: string; icon: LucideIcon }[] = [
  { id: 'password', label: 'Password', description: 'Traditional username & password login', icon: KeyRound },
  { id: 'phone_otp', label: 'Phone OTP', description: 'One-time password sent via SMS', icon: Smartphone },
  { id: 'email_otp', label: 'Email OTP', description: 'One-time password sent via email', icon: Mail },
  { id: 'magic_link', label: 'Magic Link', description: 'Passwordless login via email link', icon: Link2 },
];

export const MFA_FACTORS: readonly { id: string; label: string; description: string; icon: LucideIcon }[] = [
  { id: 'sms', label: 'SMS Code', description: 'Verification code sent via SMS', icon: Smartphone },
  { id: 'email_otp', label: 'Email OTP', description: 'One-time password sent via email', icon: Mail },
  { id: 'backup_codes', label: 'Backup Codes', description: 'Single-use recovery codes', icon: KeySquare },
];

export const ENROLLMENT_TIMING = [
  { value: 'on_registration', label: 'On Registration', description: 'Users must enroll in MFA when they register' },
  { value: 'grace_period', label: 'Grace Period', description: 'Users have a set number of days to enroll' },
  { value: 'voluntary', label: 'Voluntary', description: 'Users can opt-in to MFA at any time' },
] as const;

export const LOG_VERBOSITY = [
  { value: 'minimal', label: 'Minimal', description: 'Only critical events (logins, failures)' },
  { value: 'standard', label: 'Standard', description: 'All authentication events' },
  { value: 'verbose', label: 'Verbose', description: 'Detailed logs including OTP sends and verifications' },
] as const;

export const EVENT_TYPES = [
  'login_success',
  'login_failure',
  'logout',
  'register',
  'password_reset_request',
  'password_reset_complete',
  'password_change',
  'email_change',
  'email_verified',
  'otp_sent',
  'otp_verified',
  'otp_failed',
  'otp_expired',
  'magic_link_sent',
  'magic_link_verified',
  'mfa_enrolled',
  'mfa_unenrolled',
  'mfa_admin_bypass',
  'backup_code_used',
  'backup_codes_regenerated',
  'account_locked',
  'account_unlocked',
] as const;

export const REGISTRATION_FIELDS = [
  { id: 'phone', label: 'Phone Number' },
  { id: 'first_name', label: 'First Name' },
  { id: 'last_name', label: 'Last Name' },
] as const;

/** Matches PHP InstallManager defaults exactly. */
export const DEFAULTS: Required<AuthSettings> = {
  primary_methods: ['password'],
  mfa_factors: [],
  mfa_required_roles: [],
  enrollment_timing: 'voluntary',
  grace_period_days: 7,
  auto_create_users: false,
  auth_base_url: '/account',
  redirect_login: false,
  otp_sms_length: 6,
  otp_sms_expiry: 300,
  otp_sms_max_attempts: 5,
  otp_sms_cooldown: 60,
  otp_email_length: 6,
  otp_email_expiry: 600,
  otp_email_max_attempts: 5,
  otp_email_cooldown: 60,
  magic_link_expiry: 600,
  backup_codes_count: 10,
  backup_codes_length: 10,
  log_verbosity: 'standard',
  log_retention_days: 90,
  registration_fields: ['email', 'password'],
};

/** Toggle an item in/out of an array. */
export function toggleArrayItem<T>(arr: T[], item: T, enabled: boolean): T[] {
  return enabled ? [...arr, item] : arr.filter((x) => x !== item);
}

/** Convert snake_case to Title Case. */
export function formatLabel(value: string): string {
  return value
    .split('_')
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ');
}
