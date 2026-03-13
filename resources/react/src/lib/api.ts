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

export interface AuthSettings {
  phone?: PhoneChannelSettings;
  email?: EmailChannelSettings;
  password?: PasswordSettings;
  backup_codes?: BackupCodesSettings;
  mfa_required_roles?: string[];
  enrollment_timing?: EnrollmentTiming;
  grace_period_days?: number;
  auth_base_url?: string;
  redirect_login?: boolean;
  auto_create_users?: boolean;
  log_verbosity?: LogVerbosity;
  log_retention_days?: number;
  registration_fields?: string[];
  pending_user_cleanup_enabled?: boolean;
  pending_user_ttl_hours?: number;
}

export interface LogEntry {
  id: number;
  user_id: number;
  event: string;
  status: string;
  ip_address: string;
  context: Record<string, unknown>;
  created_at: string;
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
