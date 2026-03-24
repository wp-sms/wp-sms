import { Smartphone, Mail } from 'lucide-react';
import type { Area } from './area-nav';
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
      { value: 'whatsapp', label: 'WhatsApp', available: true },
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
  { id: 'phone', label: 'Phone Number' },
  { id: 'first_name', label: 'First Name' },
  { id: 'last_name', label: 'Last Name' },
] as const;

export const FIELD_TYPES = [
  { value: 'text', label: 'Text' },
  { value: 'textarea', label: 'Textarea' },
  { value: 'select', label: 'Select' },
  { value: 'checkbox', label: 'Checkbox' },
] as const;

export const PHONE_DISPLAY_MODES = [
  { value: 'international', label: 'International', description: 'Full number with dial code inline (e.g. +44 20 7946 0958)' },
  { value: 'separate_dial_code', label: 'Separate Dial Code', description: 'Dial code shown separately before the input (e.g. [+44] 20 7946 0958)' },
  { value: 'national', label: 'National Only', description: 'Local format only, no dial code (e.g. 020 7946 0958)' },
] as const;

export type PhoneDisplayMode = typeof PHONE_DISPLAY_MODES[number]['value'];

export const FIELD_VISIBILITY = [
  { value: 'both', label: 'Registration & Profile' },
  { value: 'registration', label: 'Registration Only' },
  { value: 'profile', label: 'Profile Only' },
  { value: 'hidden', label: 'Hidden' },
] as const;

export const FIELD_SOURCES = [
  { value: 'system', label: 'System' },
  { value: 'custom', label: 'Custom' },
  { value: 'meta', label: 'Existing Meta' },
] as const;

export const SYSTEM_FIELD_OPTIONS = [
  { id: 'email', label: 'Email' },
  { id: 'password', label: 'Password' },
  { id: 'phone', label: 'Phone Number' },
  { id: 'first_name', label: 'First Name' },
  { id: 'last_name', label: 'Last Name' },
  { id: 'display_name', label: 'Display Name' },
  { id: 'username', label: 'Username' },
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
  captcha: {
    enabled: false,
    provider: 'turnstile',
    site_key: '',
    secret_key: '',
    protected_actions: ['login', 'register', 'forgot_password'],
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
  },
  social_profile_sync: 'registration_only',
  site_phone: '',
  site_phone_channel: 'sms',
  terms_url: '',
  privacy_url: '',
  branding: {
    logo_url: '',
    site_name: '',
    logo_position: 'center',
    logo_size: 40,
    primary_color: '#171717',
    accent_color: '#6366f1',
    text_color: '#1c1917',
    error_color: '#dc2626',
    background_color: '#ffffff',
    background_image_url: '',
    color_mode: 'light',
    button_style: 'filled',
    font_family: 'system-ui',
    google_font: false,
    layout: 'centered',
    border_radius: 8,
    social_position: 'top',
    split_panel_position: 'left',
    split_panel_bg_color: '#171717',
    split_panel_bg_image_url: '',
    split_welcome_heading: 'Welcome back',
    split_subtitle: 'Sign in to continue',
  },
};

export type ChannelId = 'phone' | 'email' | 'password';

export const TRUSTED_DEVICE_TTL_OPTIONS = [
  { value: 604800, label: '7 days' },
  { value: 1209600, label: '14 days' },
  { value: 2592000, label: '30 days' },
  { value: 5184000, label: '60 days' },
  { value: 7776000, label: '90 days' },
] as const;

export const SITE_PHONE_CHANNELS = [
  { value: 'sms', label: 'SMS' },
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'telegram', label: 'Telegram' },
] as const;

export const SOCIAL_METHODS = [
  { id: 'google', label: 'Google', comingSoon: false },
  { id: 'telegram', label: 'Telegram', comingSoon: false },
  { id: 'github', label: 'GitHub', comingSoon: false },
  { id: 'apple', label: 'Apple', comingSoon: true },
  { id: 'facebook', label: 'Facebook', comingSoon: true },
  { id: 'microsoft', label: 'Microsoft', comingSoon: true },
  { id: 'linkedin', label: 'LinkedIn', comingSoon: true },
  { id: 'twitter', label: 'Twitter / X', comingSoon: true },
] as const;

// --- Contact Management Constants ---

export const CONTACT_STATUSES = [
  { value: 'subscribed', label: 'Subscribed' },
  { value: 'unsubscribed', label: 'Unsubscribed' },
  { value: 'pending', label: 'Pending' },
  { value: 'bounced', label: 'Bounced' },
  { value: 'complained', label: 'Complained' },
] as const;

export const TAG_COLORS = [
  '#ef4444', '#f97316', '#eab308', '#22c55e',
  '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899',
] as const;

export const SEGMENT_OPERATORS = {
  equals: 'Equals',
  not_equals: 'Not equals',
  contains: 'Contains',
  starts_with: 'Starts with',
  is_empty: 'Is empty',
  is_not_empty: 'Is not empty',
  has: 'Has tag',
  not_has: 'Does not have tag',
} as const;

export const ATTRIBUTE_FIELDS = [
  { value: 'email', label: 'Email' },
  { value: 'phone', label: 'Phone' },
  { value: 'first_name', label: 'First name' },
  { value: 'last_name', label: 'Last name' },
  { value: 'status', label: 'Status' },
  { value: 'source', label: 'Source' },
] as const;

export const MATCH_FIELD_OPTIONS = [
  { value: 'email', label: 'Email address' },
  { value: 'phone', label: 'Phone number' },
  { value: 'email_or_phone', label: 'Email or phone' },
] as const;

export const DUPLICATE_HANDLING_OPTIONS = [
  { value: 'update', label: 'Update existing contacts' },
  { value: 'skip', label: 'Skip duplicates' },
  { value: 'update_if_empty', label: 'Only fill empty fields' },
] as const;

export const SEGMENT_TEMPLATES = [
  { name: 'Active subscribers', conditions: { match: 'all' as const, conditions: [{ type: 'attribute' as const, field: 'status', operator: 'equals', value: 'subscribed' }] } },
  { name: 'Missing phone', conditions: { match: 'all' as const, conditions: [{ type: 'attribute' as const, field: 'phone', operator: 'is_empty' }] } },
  { name: 'Bounced contacts', conditions: { match: 'all' as const, conditions: [{ type: 'attribute' as const, field: 'status', operator: 'equals', value: 'bounced' }] } },
  { name: 'Imported contacts', conditions: { match: 'all' as const, conditions: [{ type: 'attribute' as const, field: 'source', operator: 'equals', value: 'import' }] } },
  { name: 'WordPress users', conditions: { match: 'all' as const, conditions: [{ type: 'attribute' as const, field: 'source', operator: 'equals', value: 'sync' }] } },
] as const;

// --- Area Labels ---

export const AREA_LABELS: Record<Area, string> = {
  auth: 'Authentication',
  messaging: 'Messaging',
};

// --- Integration Category Constants ---

export const INTEGRATION_CATEGORY_LABELS: Record<string, string> = {
  ecommerce: 'E-Commerce',
  cms: 'CMS',
  communication: 'Communication',
  messaging: 'Messaging',
  security: 'Security',
  forms: 'Forms',
  email_marketing: 'Email Marketing',
};

/** Integration IDs that have auth-area settings (used for cross-reference notes). */
export const AUTH_INTEGRATION_IDS = new Set(['contactform7', 'woocommerce']);

/** Toggle an item in/out of an array. */
export function toggleArrayItem<T>(arr: T[], item: T, enabled: boolean): T[] {
  return enabled ? [...arr, item] : arr.filter((x) => x !== item);
}

const ACRONYMS: Record<string, string> = {
  sms: 'SMS',
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
      return { label, detail: `List ${sourceRef}` };
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

/** Shared dark tooltip style for Recharts charts. */
export const CHART_TOOLTIP_STYLE = {
  contentStyle: { fontSize: 12, borderRadius: 8, border: 'none', backgroundColor: 'oklch(0.147 0.004 49.25)', color: '#fff' },
  labelStyle: { color: '#fff', marginBottom: 4 },
  itemStyle: { color: '#fff' },
} as const;
