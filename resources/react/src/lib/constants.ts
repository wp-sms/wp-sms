import type { LucideIcon } from 'lucide-react';
import { Smartphone, Mail } from 'lucide-react';
import type { AuthSettings } from './api';

export const CHANNELS = [
  {
    id: 'phone' as const,
    label: 'Phone',
    icon: Smartphone,
    verificationMethods: [
      { value: 'otp', label: 'OTP Code' },
      { value: 'magic_link', label: 'Magic Link (SMS link)' },
    ],
    deliveryChannels: [
      { value: 'sms', label: 'SMS', available: true },
      { value: 'whatsapp', label: 'WhatsApp', available: false },
      { value: 'viber', label: 'Viber', available: false },
    ],
  },
  {
    id: 'email' as const,
    label: 'Email',
    icon: Mail,
    verificationMethods: [
      { value: 'otp', label: 'OTP Code' },
      { value: 'magic_link', label: 'Magic Link' },
    ],
    deliveryChannels: null,
  },
] as const;

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
  'social_login_success',
  'social_login_failure',
  'social_account_linked',
  'social_account_unlinked',
  'social_registration',
] as const;

export const REGISTRATION_FIELDS = [
  { id: 'phone', label: 'Phone Number' },
  { id: 'first_name', label: 'First Name' },
  { id: 'last_name', label: 'Last Name' },
] as const;

/** Matches PHP InstallManager defaults exactly. */
export const DEFAULTS: Required<AuthSettings> = {
  phone: {
    enabled: false,
    usage: 'login',
    verification_methods: ['otp'],
    delivery_channel: 'sms',
    required_at_signup: false,
    verify_at_signup: false,
    allow_sign_in: true,
    code_length: 6,
    expiry: 300,
    max_attempts: 5,
    cooldown: 60,
  },
  email: {
    enabled: true,
    usage: 'login',
    verification_methods: ['otp'],
    required_at_signup: true,
    verify_at_signup: false,
    allow_sign_in: true,
    code_length: 6,
    expiry: 600,
    max_attempts: 5,
    cooldown: 60,
  },
  password: {
    enabled: true,
    required_at_signup: true,
    allow_sign_in: true,
  },
  backup_codes: {
    enabled: false,
    count: 10,
    length: 10,
  },
  mfa_required_roles: [],
  enrollment_timing: 'voluntary',
  grace_period_days: 7,
  auth_base_url: '/account',
  redirect_login: false,
  auto_create_users: false,
  log_verbosity: 'standard',
  log_retention_days: 90,
  registration_fields: ['email', 'password'],
  pending_user_cleanup_enabled: true,
  pending_user_ttl_hours: 24,
  captcha: {
    enabled: false,
    provider: 'turnstile',
    site_key: '',
    secret_key: '',
    protected_actions: ['login', 'register', 'forgot_password'],
    fail_open: false,
  },
  social: {
    google: { enabled: false, client_id: '', client_secret: '' },
  },
  social_profile_sync: 'registration_only',
};

export type ChannelId = 'phone' | 'email' | 'password';

export const SOCIAL_METHODS = [
  { id: 'google', label: 'Google', comingSoon: false },
  { id: 'apple', label: 'Apple', comingSoon: true },
  { id: 'facebook', label: 'Facebook', comingSoon: true },
  { id: 'microsoft', label: 'Microsoft', comingSoon: true },
  { id: 'github', label: 'GitHub', comingSoon: true },
  { id: 'linkedin', label: 'LinkedIn', comingSoon: true },
  { id: 'twitter', label: 'Twitter / X', comingSoon: true },
] as const;

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
