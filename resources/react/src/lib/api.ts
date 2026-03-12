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

export interface AuthSettings {
  primary_methods?: string[];
  mfa_factors?: string[];
  mfa_required_roles?: string[];
  enrollment_timing?: string;
  grace_period_days?: number;
  auto_create_users?: boolean;
  auth_base_url?: string;
  redirect_login?: boolean;
  otp_sms_length?: number;
  otp_sms_expiry?: number;
  otp_sms_max_attempts?: number;
  otp_sms_cooldown?: number;
  otp_email_length?: number;
  otp_email_expiry?: number;
  otp_email_max_attempts?: number;
  otp_email_cooldown?: number;
  magic_link_expiry?: number;
  backup_codes_count?: number;
  backup_codes_length?: number;
  log_verbosity?: string;
  log_retention_days?: number;
  registration_fields?: string[];
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
