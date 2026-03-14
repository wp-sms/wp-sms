declare global {
  interface Window {
    wpSmsSettings: {
      restUrl: string;
      nonce: string;
      version: string;
      adminUrl: string;
      isPremium: boolean;
      roles: Record<string, string>;
    };
  }
}

export interface ApiError {
  status: number;
  error?: string;
  message?: string;
}

export interface SettingsResponse {
  success: boolean;
  settings: AuthSettings;
  message?: string;
}

export interface LogsResponse {
  success: boolean;
  items: LogEntry[];
  total: number;
  page: number;
  per_page: number;
}

export type VerificationMethod = 'otp' | 'magic_link';
export type DeliveryChannel = 'sms' | 'whatsapp' | 'viber';
export type EnrollmentTiming = 'on_registration' | 'grace_period' | 'voluntary';
export type LogVerbosity = 'minimal' | 'standard' | 'verbose';

export interface ChannelSettings {
  enabled?: boolean;
  usage?: 'login' | 'mfa';
  verification_methods?: VerificationMethod[];
  required_at_signup?: boolean;
  verify_at_signup?: boolean;
  allow_sign_in?: boolean;
  code_length?: number;
  expiry?: number;
  max_attempts?: number;
  cooldown?: number;
}

export interface PhoneChannelSettings extends ChannelSettings {
  delivery_channel?: DeliveryChannel;
}

export type EmailChannelSettings = ChannelSettings;

export interface PasswordSettings {
  enabled?: boolean;
  required_at_signup?: boolean;
  allow_sign_in?: boolean;
}

export interface BackupCodesSettings {
  enabled?: boolean;
  count?: number;
  length?: number;
}

export interface TotpSettings {
  enabled?: boolean;
}

export type CaptchaProvider = 'turnstile' | 'recaptcha' | 'hcaptcha';
export type CaptchaAction = 'login' | 'register' | 'forgot_password' | 'identify';

export interface CaptchaSettings {
  enabled?: boolean;
  provider?: CaptchaProvider;
  site_key?: string;
  secret_key?: string;
  protected_actions?: CaptchaAction[];
  fail_open?: boolean;
}

export interface SocialProviderSettings {
  enabled?: boolean;
  client_id?: string;
  client_secret?: string;
}

export type SocialProfileSync = 'registration_only' | 'every_login';

export interface TelegramSettings {
  bot_token?: string;
  bot_username?: string;
  webhook_secret?: string;
  enabled?: boolean;
  code_length?: number;
  expiry?: number;
  max_attempts?: number;
  cooldown?: number;
}

export type FieldType = 'text' | 'textarea' | 'select' | 'checkbox';
export type FieldSource = 'system' | 'custom' | 'meta';
export type FieldVisibility = 'registration' | 'profile' | 'both' | 'hidden';

export interface ProfileFieldDefinition {
  id: string;
  type: FieldType;
  label: string;
  source: FieldSource;
  meta_key: string;
  visibility: FieldVisibility;
  required: boolean;
  sort_order: number;
  placeholder?: string;
  options?: { value: string; label: string }[];
  description?: string;
  default_value?: string | boolean;
}

export interface MetaKeyInfo {
  key: string;
  sample_value: string;
  count: number;
}

export interface AuthSettings {
  phone?: PhoneChannelSettings;
  email?: EmailChannelSettings;
  password?: PasswordSettings;
  backup_codes?: BackupCodesSettings;
  totp?: TotpSettings;
  captcha?: CaptchaSettings;
  telegram?: TelegramSettings;
  mfa_required_roles?: string[];
  enrollment_timing?: EnrollmentTiming;
  grace_period_days?: number;
  auth_base_url?: string;
  redirect_login?: boolean;
  auto_create_users?: boolean;
  log_verbosity?: LogVerbosity;
  log_retention_days?: number;
  registration_fields?: string[];
  profile_fields?: ProfileFieldDefinition[];
  pending_user_cleanup_enabled?: boolean;
  pending_user_ttl_hours?: number;
  social?: Record<string, SocialProviderSettings>;
  social_profile_sync?: SocialProfileSync;
}

export interface LogEntry {
  id: number;
  user_id: number;
  event: string;
  status: string;
  ip_address: string;
  channel_id: string | null;
  user_agent: string | null;
  meta: string | Record<string, unknown> | null;
  created_at: string;
  user_display: { display_name: string; email: string } | null;
}

const FALLBACK_CONFIG = { restUrl: '', nonce: '', version: '', adminUrl: '', isPremium: false, roles: {} as Record<string, string> };

export function getConfig() {
  return window.wpSmsSettings ?? FALLBACK_CONFIG;
}

async function request<T>(method: string, endpoint: string, body?: unknown): Promise<T> {
  const { restUrl, nonce } = getConfig();

  const opts: RequestInit = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    credentials: 'same-origin',
  };

  if (body) {
    opts.body = JSON.stringify(body);
  }

  const res = await fetch(`${restUrl}${endpoint.replace(/^\//, '')}`, opts);
  const data = await res.json();

  if (!res.ok) {
    throw { status: res.status, ...data } as ApiError;
  }

  return data as T;
}

export const api = {
  get: <T>(url: string) => request<T>('GET', url),
  put: <T>(url: string, body: unknown) => request<T>('PUT', url, body),
  del: <T>(url: string) => request<T>('DELETE', url),
};

export async function getMetaKeys(): Promise<MetaKeyInfo[]> {
  const res = await api.get<{ success: boolean; meta_keys: MetaKeyInfo[] }>('/wsms/v1/auth/admin/meta-keys');
  return res.meta_keys;
}
