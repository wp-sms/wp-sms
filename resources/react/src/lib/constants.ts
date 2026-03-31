import { __, sprintf } from '@wordpress/i18n';
import { Smartphone, Mail } from 'lucide-react';
import type { AuthSettings } from './api';

export const CHANNELS = [
  {
    id: 'phone' as const,
    label: __('Phone', 'wp-sms'),
    icon: Smartphone,
    verificationMethods: [
      { value: 'otp', label: __('OTP Code', 'wp-sms') },
      { value: 'magic_link', label: __('Magic Link (SMS link)', 'wp-sms') },
    ],
    deliveryChannels: [
      { value: 'sms', label: __('SMS', 'wp-sms'), available: true },
      { value: 'whatsapp', label: __('WhatsApp', 'wp-sms'), available: true },
      { value: 'rcs', label: __('RCS', 'wp-sms'), available: true },
      { value: 'viber', label: __('Viber', 'wp-sms'), available: false },
    ],
  },
  {
    id: 'email' as const,
    label: __('Email', 'wp-sms'),
    icon: Mail,
    verificationMethods: [
      { value: 'otp', label: __('OTP Code', 'wp-sms') },
      { value: 'magic_link', label: __('Magic Link', 'wp-sms') },
    ],
    deliveryChannels: null,
  },
] as const;

export const ENROLLMENT_TIMING = [
  { value: 'on_registration', label: __('On Registration', 'wp-sms'), description: __('Users must enroll in MFA when they register', 'wp-sms') },
  { value: 'grace_period', label: __('Grace Period', 'wp-sms'), description: __('Users have a set number of days to enroll', 'wp-sms') },
  { value: 'voluntary', label: __('Voluntary', 'wp-sms'), description: __('Users can opt-in to MFA at any time', 'wp-sms') },
] as const;

export const LOG_VERBOSITY = [
  { value: 'minimal', label: __('Minimal', 'wp-sms'), description: __('Only critical events (logins, failures)', 'wp-sms') },
  { value: 'standard', label: __('Standard', 'wp-sms'), description: __('All authentication events', 'wp-sms') },
  { value: 'verbose', label: __('Verbose', 'wp-sms'), description: __('Detailed logs including OTP sends and verifications', 'wp-sms') },
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
  'totp_verified',
  'totp_failed',
  'passkey_verified',
  'passkey_failed',
  'account_locked',
  'account_unlocked',
  'social_login_success',
  'social_login_failure',
  'social_account_linked',
  'social_account_unlinked',
  'social_registration',
  'account_suspended',
  'account_unsuspended',
] as const;

export const REGISTRATION_FIELDS = [
  { id: 'phone', label: __('Phone Number', 'wp-sms') },
  { id: 'first_name', label: __('First Name', 'wp-sms') },
  { id: 'last_name', label: __('Last Name', 'wp-sms') },
] as const;

export const FIELD_TYPES = [
  { value: 'text', label: __('Text', 'wp-sms') },
  { value: 'textarea', label: __('Textarea', 'wp-sms') },
  { value: 'select', label: __('Select', 'wp-sms') },
  { value: 'checkbox', label: __('Checkbox', 'wp-sms') },
] as const;

export const PHONE_DISPLAY_MODES = [
  { value: 'international', label: __('International', 'wp-sms'), description: __('Full number with dial code inline (e.g. +44 20 7946 0958)', 'wp-sms') },
  { value: 'separate_dial_code', label: __('Separate Dial Code', 'wp-sms'), description: __('Dial code shown separately before the input (e.g. [+44] 20 7946 0958)', 'wp-sms') },
  { value: 'national', label: __('National Only', 'wp-sms'), description: __('Local format only, no dial code (e.g. 020 7946 0958)', 'wp-sms') },
] as const;

export type PhoneDisplayMode = typeof PHONE_DISPLAY_MODES[number]['value'];

export const FIELD_VISIBILITY = [
  { value: 'both', label: __('Registration & Profile', 'wp-sms') },
  { value: 'registration', label: __('Registration Only', 'wp-sms') },
  { value: 'profile', label: __('Profile Only', 'wp-sms') },
  { value: 'hidden', label: __('Hidden', 'wp-sms') },
] as const;

export const FIELD_SOURCES = [
  { value: 'system', label: __('System', 'wp-sms') },
  { value: 'custom', label: __('Custom', 'wp-sms') },
  { value: 'meta', label: __('Existing Meta', 'wp-sms') },
] as const;

export const SYSTEM_FIELD_OPTIONS = [
  { id: 'email', label: __('Email', 'wp-sms') },
  { id: 'password', label: __('Password', 'wp-sms') },
  { id: 'phone', label: __('Phone Number', 'wp-sms') },
  { id: 'first_name', label: __('First Name', 'wp-sms') },
  { id: 'last_name', label: __('Last Name', 'wp-sms') },
  { id: 'display_name', label: __('Display Name', 'wp-sms') },
  { id: 'username', label: __('Username', 'wp-sms') },
] as const;

/** Must match PHP SettingsRepository::DEFAULTS — enforced by test. */
export const DEFAULTS: Required<AuthSettings> = {
  auth_enabled: false,
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
    max_attempts: 3,
    cooldown: 60,
    reverify_on_change: false,
    otp_gateway: null,
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
    max_attempts: 3,
    cooldown: 60,
    reverify_on_change: false,
    otp_gateway: null,
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
  totp: {
    enabled: false,
  },
  passkey: {
    enabled: false,
  },
  trusted_devices: {
    enabled: false,
    ttl: 2592000,
  },
  mfa_required_roles: [],
  enrollment_timing: 'voluntary',
  grace_period_days: 7,
  auth_base_url: '/account',
  redirect_login: false,
  auto_create_users: false,
  log_verbosity: 'standard',
  log_retention_days: 30,
  registration_fields: ['email', 'password'],
  profile_fields: [],
  pending_user_cleanup_enabled: true,
  pending_user_ttl_hours: 24,
  telegram: {
    bot_token: '',
    bot_username: '',
    webhook_secret: '',
    enabled: false,
    code_length: 6,
    expiry: 300,
    max_attempts: 3,
    cooldown: 60,
  },
  line: {
    enabled: false,
    bot_basic_id: '',
    code_length: 6,
    expiry: 300,
    max_attempts: 3,
    cooldown: 60,
  },
  captcha: {
    enabled: false,
    provider: 'turnstile',
    site_key: '',
    secret_key: '',
    protected_actions: ['login', 'register', 'forgot_password', 'subscribe', 'messaging_button'],
    fail_open: false,
  },
  woocommerce: {
    verify_email_at_checkout: false,
    verify_phone_at_checkout: false,
    skip_verified_users: true,
    redirect_auth: false,
  },
  contact_form_7: {
    verification_enabled: true,
    notifications_enabled: true,
  },
  social: {
    google: { enabled: false, client_id: '', client_secret: '' },
    github: { enabled: false, client_id: '', client_secret: '' },
    telegram: { enabled: false, client_id: '', client_secret: '' },
    line: { enabled: false, client_id: '', client_secret: '' },
  },
  social_profile_sync: 'registration_only',
  site_phone: '',
  site_phone_channel: 'sms',
  terms_url: '',
  privacy_url: '',
  subscription_consent_text: '',
  subscription_consent_required: false,
};

export type ChannelId = 'phone' | 'email' | 'password';

export const TRUSTED_DEVICE_TTL_OPTIONS = [
  { value: 604800, label: __('7 days', 'wp-sms') },
  { value: 1209600, label: __('14 days', 'wp-sms') },
  { value: 2592000, label: __('30 days', 'wp-sms') },
  { value: 5184000, label: __('60 days', 'wp-sms') },
  { value: 7776000, label: __('90 days', 'wp-sms') },
] as const;

export const SITE_PHONE_CHANNELS = [
  { value: 'sms', label: __('SMS', 'wp-sms') },
  { value: 'whatsapp', label: __('WhatsApp', 'wp-sms') },
  { value: 'telegram', label: __('Telegram', 'wp-sms') },
  { value: 'line', label: __('LINE', 'wp-sms') },
] as const;

export const SOCIAL_METHODS = [
  { id: 'google', label: __('Google', 'wp-sms'), comingSoon: false },
  { id: 'telegram', label: __('Telegram', 'wp-sms'), comingSoon: false },
  { id: 'line', label: __('LINE', 'wp-sms'), comingSoon: false },
  { id: 'github', label: __('GitHub', 'wp-sms'), comingSoon: false },
  { id: 'apple', label: __('Apple', 'wp-sms'), comingSoon: true },
  { id: 'facebook', label: __('Facebook', 'wp-sms'), comingSoon: true },
  { id: 'microsoft', label: __('Microsoft', 'wp-sms'), comingSoon: true },
  { id: 'linkedin', label: __('LinkedIn', 'wp-sms'), comingSoon: true },
  { id: 'twitter', label: __('Twitter / X', 'wp-sms'), comingSoon: true },
] as const;

// --- Contact Management Constants ---

export const CONTACT_STATUSES = [
  { value: 'subscribed', label: __('Subscribed', 'wp-sms') },
  { value: 'pending', label: __('Pending', 'wp-sms') },
  { value: 'bounced', label: __('Bounced', 'wp-sms') },
  { value: 'complained', label: __('Complained', 'wp-sms') },
] as const;

export function contactStatusLabel(status: string): string {
  return CONTACT_STATUSES.find(s => s.value === status)?.label ?? formatLabel(status);
}

export const OPT_OUT_CHANNELS = [
  { value: 'sms', label: __('SMS', 'wp-sms') },
  { value: 'email', label: __('Email', 'wp-sms') },
] as const;

export const TAG_COLORS = [
  '#ef4444', '#f97316', '#eab308', '#22c55e',
  '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899',
] as const;

export const SEGMENT_OPERATORS = {
  equals: __('Equals', 'wp-sms'),
  not_equals: __('Not equals', 'wp-sms'),
  contains: __('Contains', 'wp-sms'),
  starts_with: __('Starts with', 'wp-sms'),
  is_empty: __('Is empty', 'wp-sms'),
  is_not_empty: __('Is not empty', 'wp-sms'),
  has: __('Has tag', 'wp-sms'),
  not_has: __('Does not have tag', 'wp-sms'),
} as const;

export const ATTRIBUTE_FIELDS = [
  { value: 'email', label: __('Email', 'wp-sms') },
  { value: 'phone', label: __('Phone', 'wp-sms') },
  { value: 'first_name', label: __('First name', 'wp-sms') },
  { value: 'last_name', label: __('Last name', 'wp-sms') },
  { value: 'status', label: __('Status', 'wp-sms') },
  { value: 'channel_opt_outs', label: __('Channel opt-outs', 'wp-sms') },
  { value: 'source', label: __('Source', 'wp-sms') },
] as const;

export const MATCH_FIELD_OPTIONS = [
  { value: 'email', label: __('Email address', 'wp-sms') },
  { value: 'phone', label: __('Phone number', 'wp-sms') },
  { value: 'email_or_phone', label: __('Email or phone', 'wp-sms') },
] as const;

export const DUPLICATE_HANDLING_OPTIONS = [
  { value: 'update', label: __('Update existing contacts', 'wp-sms') },
  { value: 'skip', label: __('Skip duplicates', 'wp-sms') },
  { value: 'update_if_empty', label: __('Only fill empty fields', 'wp-sms') },
] as const;

export const SEGMENT_TEMPLATES = [
  { name: __('Active subscribers', 'wp-sms'), conditions: { match: 'all' as const, conditions: [{ type: 'attribute' as const, field: 'status', operator: 'equals', value: 'subscribed' }] } },
  { name: __('Missing phone', 'wp-sms'), conditions: { match: 'all' as const, conditions: [{ type: 'attribute' as const, field: 'phone', operator: 'is_empty' }] } },
  { name: __('Bounced contacts', 'wp-sms'), conditions: { match: 'all' as const, conditions: [{ type: 'attribute' as const, field: 'status', operator: 'equals', value: 'bounced' }] } },
  { name: __('Imported contacts', 'wp-sms'), conditions: { match: 'all' as const, conditions: [{ type: 'attribute' as const, field: 'source', operator: 'equals', value: 'import' }] } },
  { name: __('WordPress users', 'wp-sms'), conditions: { match: 'all' as const, conditions: [{ type: 'attribute' as const, field: 'source', operator: 'equals', value: 'sync' }] } },
] as const;

// --- Integration Category Constants ---

export const INTEGRATION_CATEGORY_LABELS: Record<string, string> = {
  ecommerce: __('E-Commerce', 'wp-sms'),
  cms: __('CMS', 'wp-sms'),
  communication: __('Communication', 'wp-sms'),
  messaging: __('Messaging', 'wp-sms'),
  security: __('Security', 'wp-sms'),
  forms: __('Forms', 'wp-sms'),
  email_marketing: __('Email Marketing', 'wp-sms'),
  automation: __('Automation', 'wp-sms'),
  contacts: __('Contacts', 'wp-sms'),
};

/** Integration IDs that have auth-area settings (used for cross-reference notes). */
export const AUTH_INTEGRATION_IDS = new Set(['contactform7', 'woocommerce']);

/** Toggle an item in/out of an array. */
export function toggleArrayItem<T>(arr: T[], item: T, enabled: boolean): T[] {
  return enabled ? [...arr, item] : arr.filter((x) => x !== item);
}

const ACRONYMS: Record<string, string> = {
  sms: 'SMS',
  rcs: 'RCS',
  otp: 'OTP',
  mfa: 'MFA',
  totp: 'TOTP',
  url: 'URL',
  api: 'API',
  http: 'HTTP',
  id: 'ID',
  ip: 'IP',
  csv: 'CSV',
  json: 'JSON',
};

export function formatSource(source: string, sourceRef?: string | null): { label: string; detail?: string } {
  const label = formatLabel(source);
  if (!sourceRef) return { label };

  switch (source) {
    case 'messaging_button': {
      try {
        return { label, detail: new URL(sourceRef).pathname };
      } catch {
        return { label, detail: sourceRef };
      }
    }
    case 'emailoctopus':
      return { label, detail: sprintf(__('List %s', 'wp-sms'), sourceRef) };
    default:
      return { label, detail: sourceRef };
  }
}

/** Convert snake_case or kebab-case to Title Case, with correct acronym casing. */
export function formatLabel(value: string): string {
  return value
    .replace(/[_-]/g, ' ')
    .replace(/\b\w+\b/g, (word) => {
      const lower = word.toLowerCase();
      return ACRONYMS[lower] ?? (word.charAt(0).toUpperCase() + word.slice(1));
    });
}
