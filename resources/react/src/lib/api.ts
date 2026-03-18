declare global {
  interface Window {
    wpSmsSettings: {
      restUrl: string;
      nonce: string;
      version: string;
      adminUrl: string;
      isPremium: boolean;
      roles: Record<string, string>;
      area: 'auth' | 'messaging';
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
  reverify_on_change?: boolean;
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

export interface WooCommerceSettings {
  verify_email_at_checkout?: boolean;
  verify_phone_at_checkout?: boolean;
  skip_verified_users?: boolean;
  redirect_auth?: boolean;
}

export interface AuthSettings {
  phone?: PhoneChannelSettings;
  email?: EmailChannelSettings;
  password?: PasswordSettings;
  backup_codes?: BackupCodesSettings;
  totp?: TotpSettings;
  captcha?: CaptchaSettings;
  telegram?: TelegramSettings;
  woocommerce?: WooCommerceSettings;
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

export interface ReportsResponse {
  success: boolean;
  range: number;
  auth_activity: {
    total_logins: number;
    successful_logins: number;
    failed_logins: number;
    login_success_rate: number;
    total_registrations: number;
    password_resets: number;
    timeline: { date: string; logins: number; failures: number; registrations: number }[];
  };
  user_security: {
    total_users: number;
    mfa_enrolled: number;
    mfa_adoption_rate: number;
    email_verified: number;
    email_verification_rate: number;
    phone_verified: number;
    phone_verification_rate: number;
    suspended_users: number;
  };
  channel_usage: {
    login_methods: { method: string; count: number }[];
    mfa_methods: { method: string; count: number }[];
    social_providers: { provider: string; count: number }[];
  };
  security_alerts: {
    failed_login_attempts: number;
    accounts_locked: number;
    accounts_suspended: number;
    otp_failures: number;
    top_failed_ips: { ip: string; count: number }[];
    recent_lockouts: { user_id: number; display_name: string; locked_at: string; ip: string }[];
    recent_suspensions: { user_id: number; display_name: string; suspended_at: string; ip: string }[];
  };
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

const FALLBACK_CONFIG = { restUrl: '', nonce: '', version: '', adminUrl: '', isPremium: false, roles: {} as Record<string, string>, area: 'auth' as const };

export function getConfig() {
  return window.wpSmsSettings ?? FALLBACK_CONFIG;
}

async function request<T>(method: string, endpoint: string, body?: unknown, signal?: AbortSignal): Promise<T> {
  const { restUrl, nonce } = getConfig();

  const opts: RequestInit = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    credentials: 'same-origin',
    signal,
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

interface RequestOptions {
  signal?: AbortSignal;
}

export const api = {
  get: <T>(url: string, opts?: RequestOptions) => request<T>('GET', url, undefined, opts?.signal),
  post: <T>(url: string, body: unknown, opts?: RequestOptions) => request<T>('POST', url, body, opts?.signal),
  put: <T>(url: string, body: unknown, opts?: RequestOptions) => request<T>('PUT', url, body, opts?.signal),
  del: <T>(url: string, opts?: RequestOptions) => request<T>('DELETE', url, undefined, opts?.signal),
  upload: <T>(url: string, formData: FormData, opts?: RequestOptions) => uploadFormData<T>(url, formData, opts?.signal),
};

async function uploadFormData<T>(endpoint: string, formData: FormData, signal?: AbortSignal): Promise<T> {
  const { restUrl, nonce } = getConfig();
  const res = await fetch(`${restUrl}${endpoint.replace(/^\//, '')}`, {
    method: 'POST',
    headers: { 'X-WP-Nonce': nonce },
    credentials: 'same-origin',
    body: formData,
    signal,
  });
  const data = await res.json();
  if (!res.ok) throw { status: res.status, ...data } as ApiError;
  return data as T;
}

export async function getMetaKeys(): Promise<MetaKeyInfo[]> {
  const res = await api.get<{ success: boolean; meta_keys: MetaKeyInfo[] }>('auth/admin/meta-keys');
  return res.meta_keys;
}

// --- Messaging Platform Types ---

export interface JsonSchemaProperty {
  type: string;
  title?: string;
  description?: string;
  hint?: string;
  enum?: string[];
  default?: unknown;
  items?: JsonSchemaProperty;
  properties?: Record<string, JsonSchemaProperty>;
  required?: string[];
  dynamic?: boolean;
  example?: unknown;
  template?: boolean;
  displayOptions?: {
    show?: Record<string, unknown[]>;
    hide?: Record<string, unknown[]>;
  };
  dependsOn?: string[];
}

export interface JsonSchema {
  type?: string;
  title?: string;
  description?: string;
  properties?: Record<string, JsonSchemaProperty>;
  required?: string[];
}

// --- Flow Node Types (matches backend FlowExecutor node shapes) ---

interface FlowNodeBase {
  id: string;
}

export interface ErrorHandlingConfig {
  behavior: 'stop' | 'continue' | 'retry';
  maxRetries?: number;
  retryIntervalSecs?: number;
  continueOnExhausted?: boolean;
}

export interface ActionNode extends FlowNodeBase {
  type: 'action';
  action: string;
  config: Record<string, unknown>;
  onError?: ErrorHandlingConfig;
}

export interface ConditionRule {
  field: string;
  operator: string;
  value: string;
}

export interface ConditionNode extends FlowNodeBase {
  type: 'condition';
  expression: string;
  rules?: ConditionRule[];
  then: FlowNode[];
  else: FlowNode[];
}

export interface DelayNode extends FlowNodeBase {
  type: 'delay';
  duration: number;
  then: FlowNode[];
}

export type FlowNode = ActionNode | ConditionNode | DelayNode;


export interface Flow {
  id: string;
  name: string;
  trigger_type: string;
  trigger_config: Record<string, unknown>;
  steps: FlowNode[];
  status: 'draft' | 'active' | 'paused';
  published_steps: FlowNode[] | null;
  published_at: string | null;
  description: string | null;
  priority: number;
  created_by: number | null;
}

export interface Contact {
  id: string;
  email: string;
  phone: string;
  first_name: string;
  last_name: string;
  status: string;
  source: string;
  custom_fields: Record<string, unknown>;
  wp_user_id?: string | null;
  created_at?: string;
  updated_at?: string;
  tags?: Tag[];
}

export interface ContactDetail extends Contact {
  tags: Tag[];
  wp_user?: { username: string; roles: string[]; registered: string; edit_url: string };
}

export interface Tag {
  id: string;
  name: string;
  slug: string;
  color: string;
  contact_count?: number;
}

export interface ContactList {
  id: string;
  name: string;
  type: 'static' | 'dynamic';
  conditions: SegmentConditionGroup | null;
  tag_id: string | null;
  description: string | null;
  contact_count: number;
  created_at: string;
  updated_at: string;
}

export interface SegmentConditionGroup {
  match: 'all' | 'any';
  conditions?: SegmentCondition[];
  groups?: SegmentConditionGroup[];
}

export interface SegmentCondition {
  type: 'attribute' | 'tag';
  field?: string;
  operator: string;
  value?: string;
}

export interface ContactActivity {
  id: string;
  type: string;
  description: string;
  meta: Record<string, unknown>;
  created_at: string;
}

export interface ImportResult {
  imported: number;
  updated: number;
  skipped: number;
  errors: string[];
}

export interface ImportPreview {
  headers: string[];
  rows: string[][];
}

export interface GatewayConfigField {
  type: string;
  label: string;
  required?: boolean;
  description?: string;
  placeholder?: string;
  default?: unknown;
  options?: { value: string; label: string }[];
}

export interface GatewayConfigSchema {
  shared: Record<string, GatewayConfigField>;
  channels: Record<string, Record<string, GatewayConfigField>>;
}

export interface GatewayMetadata {
  description?: string;
  website?: string;
  icon?: string;
  regions?: string[];
  setup_url?: string;
  setup_notes?: string[];
}

export interface GatewayConfig {
  shared: Record<string, unknown>;
  channels: Record<string, Record<string, unknown>>;
  is_default: Record<string, boolean>;
}

export interface Gateway {
  id: string;
  name: string;
  supported_channels: string[];
  config_schema: GatewayConfigSchema;
  is_configured: boolean;
  config: GatewayConfig;
  metadata: GatewayMetadata;
  features: Record<string, boolean>;
}

export interface GatewayTestResult {
  success: boolean;
  data: {
    status: string;
    provider_id: string | null;
    error: string | null;
  };
}

export interface MessageLogEntry {
  id: string;
  execution_id: string | null;
  gateway_id: string;
  channel: string;
  type: string;
  recipient: string;
  subject: string | null;
  body_preview: string | null;
  status: string;
  provider_id: string | null;
  error: string | null;
  cost: string | null;
  sent_at: string | null;
  delivered_at: string | null;
  created_at: string;
}

export interface TriggerDefinition {
  id: string;
  name: string;
  group: string;
  payload_schema: JsonSchema;
  filter_schema: Record<string, JsonSchemaProperty>;
}

export interface ActionDefinition {
  id: string;
  name: string;
  group: string;
  config_schema: JsonSchema;
  placeholders?: Record<string, Record<string, string>>;
}

export interface PlatformIntegration {
  id: string;
  name: string;
  category: string;
  icon: string;
  available: boolean;
  auth_type: string;
  triggers: number;
  actions: number;
}

export interface FlowTemplate {
  id: string;
  name: string;
  description: string;
  category: string;
  trigger_type: string;
  trigger_config: Record<string, unknown>;
  steps: FlowNode[];
}

export interface ListResponse<T> {
  items: T[];
  total: number;
}

// --- Flow Execution Types ---

export interface StepLog {
  node_id: string;
  type: string;
  status: 'started' | 'completed' | 'failed' | 'retrying';
  input?: Record<string, unknown>;
  output?: Record<string, unknown>;
  error?: string;
  at: string;
}

export interface FlowExecution {
  id: string;
  flow_id: string;
  trigger_data: Record<string, unknown>;
  status: 'pending' | 'running' | 'completed' | 'failed' | 'waiting';
  step_logs: StepLog[];
  error: string | null;
  started_at: string;
  completed_at: string | null;
}
