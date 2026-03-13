/** Auth settings object matching PolicyEngine::CHANNEL_DEFAULTS structure. */
export interface AuthSettings {
  password?: {
    enabled?: boolean;
    required_at_signup?: boolean;
    allow_sign_in?: boolean;
  };
  email?: {
    enabled?: boolean;
    usage?: 'login' | 'mfa';
    verification_methods?: ('otp' | 'magic_link')[];
    allow_sign_in?: boolean;
    verify_at_signup?: boolean;
    verify_at_login?: boolean;
    code_length?: number;
    expiry?: number;
    max_attempts?: number;
  };
  phone?: {
    enabled?: boolean;
    usage?: 'login' | 'mfa';
    verification_methods?: ('otp' | 'magic_link')[];
    allow_sign_in?: boolean;
    required_at_signup?: boolean;
    verify_at_signup?: boolean;
    verify_at_login?: boolean;
    code_length?: number;
    expiry?: number;
    max_attempts?: number;
  };
  backup_codes?: {
    enabled?: boolean;
  };
  social?: {
    google?: { enabled?: boolean; client_id?: string; client_secret?: string };
    telegram?: { enabled?: boolean; client_id?: string; client_secret?: string };
  };
  telegram?: {
    bot_token?: string;
    bot_username?: string;
    webhook_secret?: string;
    mfa_enabled?: boolean;
    code_length?: number;
    expiry?: number;
    max_attempts?: number;
    cooldown?: number;
  };
  mfa_required_roles?: string[];
  enrollment_timing?: 'on_registration' | 'grace_period' | 'voluntary';
  grace_period_days?: number;
  registration_fields?: string[];
}

export interface E2eResponse<T = Record<string, unknown>> {
  ok: boolean;
  error?: string;
  [key: string]: unknown;
}

export interface CreateUserParams {
  email: string;
  password?: string;
  username?: string;
  role?: string;
  phone?: string;
  meta?: Record<string, unknown>;
  auto_enroll_phone?: boolean;
}

export interface CreateUserResponse {
  ok: boolean;
  user_id: number;
  email: string;
  username: string;
  role: string;
}

export interface OtpResponse {
  ok: boolean;
  otp?: string;
  type?: string;
  error?: string;
}

export interface MagicLinkResponse {
  ok: boolean;
  token?: string;
  error?: string;
}

export interface NonceResponse {
  ok: boolean;
  nonce: string;
}

export interface CleanupResponse {
  ok: boolean;
  users_deleted: number;
}

export interface AuthResult {
  success: boolean;
  message?: string;
  stage?: string;
  user?: {
    id: number;
    email: string;
    display_name: string;
    roles: string[];
  };
  pending_verifications?: string[];
  data?: Record<string, unknown>;
}

export interface IdentifyResult {
  success: boolean;
  methods?: Array<{
    id: string;
    label: string;
  }>;
  message?: string;
}

export interface ConfigResult {
  methods: Array<{
    id: string;
    label: string;
    type: string;
  }>;
  registration_fields: string[];
}
