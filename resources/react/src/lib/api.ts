declare global {
  interface Window {
    wpSmsSettings: {
      restUrl: string;
      nonce: string;
      version: string;
      adminUrl: string;
      isPremium: boolean;
      roles: Record<string, string>;
      timezone: string;
      area: string;
      phoneInput?: {
        displayMode?: string;
        defaultCountry?: string;
      };
      currentUserHasMfa: boolean;
      currentUserRoles: string[];
      capabilities: WsmsCapabilities;
      hasExtensions: boolean;
      pluginUrl: string;
      isRtl: boolean;
      onboarding: OnboardingState | null;
      locale: string;
      detectedIntegrations: DetectedIntegration[];
      detectedMigrations: DetectedMigration[];
    };
  }
}

// --- Access / Capabilities ---

export interface WsmsCapabilities {
  is_admin: boolean;
  wsms_view_dashboard: boolean;
  wsms_view_audience: boolean;
  wsms_manage_audience: boolean;
  wsms_view_campaigns: boolean;
  wsms_manage_campaigns: boolean;
  wsms_view_automation: boolean;
  wsms_manage_automation: boolean;
  wsms_view_channels: boolean;
  wsms_manage_channels: boolean;
  wsms_view_identity: boolean;
  wsms_manage_identity: boolean;
  wsms_view_monitoring: boolean;
  wsms_manage_monitoring: boolean;
  wsms_manage_settings: boolean;
}

// --- Onboarding Types ---

export type OnboardingStatus = 'pending' | 'in_progress' | 'completed' | 'skipped';
export type OnboardingGoal = 'auth' | 'notifications' | 'campaigns';

export interface ChannelToggles {
  email: boolean;
  phone: boolean;
  password: boolean;
}

export interface OnboardingState {
  status: OnboardingStatus;
  goals: OnboardingGoal[];
  current_step: number;
  skipped_steps: number[];
  checklist_dismissed: boolean;
  migration_deferred: boolean;
  completed_at: string | null;
  version: string;
}

export interface DetectedIntegration {
  id: string;
  name: string;
  icon: string;
  goals: string[];
}

export interface DetectedMigration {
  id: string;
  name: string;
  active: boolean;
  version: string | null;
  summary: string[];
  migrationStatus: string | null;
}

export interface ChecklistItem {
  id: string;
  label: string;
  route: string;
  completed: boolean;
  goals: string[];
}

export interface OnboardingResponse {
  success: boolean;
  data: {
    state: OnboardingState;
    checklist: ChecklistItem[];
  };
}

export interface ApiError {
  status: number;
  error?: string;
  message?: string;
}

export interface SocialProviderMeta {
  id: string;
  name: string;
  icon_svg: string;
  builtin: boolean;
}

export interface MfaChannelMeta {
  id: string;
  name: string;
  icon_svg: string;
  description: string;
  supports_mfa: boolean;
  supports_auth: boolean;
  builtin: boolean;
  settings_key: string;
  config_schema: JsonSchema | null;
}

export interface LayoutNode {
  component: 'form' | 'table' | 'tabs' | 'stats' | 'status' | 'alert' | 'text' | 'actions';
  endpoint?: string;
  columns?: { key: string; label: string }[];
  children?: (LayoutNode & { label?: string })[];
  buttons?: { label: string; endpoint: string; method: string; variant?: string; confirm?: string }[];
  variant?: string;
  message?: string;
  content?: string;
  searchable?: boolean;
  actions?: { label: string; endpoint: string; method: string; confirm?: string }[];
  test_endpoint?: string;
}

export interface ExtensionPageLayout {
  title: string;
  icon?: string;
  layout: LayoutNode[];
}

export interface ExtensionInfo {
  id: string;
  name: string;
  description: string;
  version: string;
  type: string;
  requires: string;
  page?: ExtensionPageLayout;
}

export interface SettingsResponse {
  success: boolean;
  settings: AuthSettings;
  message?: string;
  social_providers?: SocialProviderMeta[];
  mfa_channels?: MfaChannelMeta[];
}

export interface LogsResponse {
  success: boolean;
  items: LogEntry[];
  total: number;
  page: number;
  per_page: number;
}

export type VerificationMethod = 'otp' | 'magic_link';
export type DeliveryChannel = 'sms' | 'whatsapp' | 'rcs' | 'viber';
export type SitePhoneChannel = 'sms' | 'whatsapp' | 'telegram';
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
  otp_gateway?: string | null;
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

export interface PasskeySettings {
  enabled?: boolean;
}

export type CaptchaProvider = 'turnstile' | 'recaptcha' | 'hcaptcha';
export type CaptchaAction = 'login' | 'register' | 'forgot_password' | 'identify' | 'subscribe' | 'messaging_button';

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
export type ColorMode = 'light' | 'dark' | 'auto';
export type ButtonStyle = 'filled' | 'outline' | 'ghost';

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

export interface LineSettings {
  enabled?: boolean;
  bot_basic_id?: string;
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

export interface ContactForm7Settings {
  verification_enabled?: boolean;
  notifications_enabled?: boolean;
}

export type BrandingLayout = 'centered' | 'split';
export type SplitPanelPosition = 'left' | 'right';
export type LogoPosition = 'left' | 'center' | 'right' | 'hidden';
export type SocialPosition = 'top' | 'bottom';

export interface BrandingSettings {
  logo_url: string;
  site_name: string;
  logo_position: LogoPosition;
  logo_size: number;
  primary_color: string;
  accent_color: string;
  text_color: string;
  error_color: string;
  background_color: string;
  background_image_url: string;
  color_mode: ColorMode;
  button_style: ButtonStyle;
  font_family: string;
  google_font: boolean;
  layout: BrandingLayout;
  border_radius: number;
  social_position: SocialPosition;
  split_panel_position: SplitPanelPosition;
  split_panel_bg_color: string;
  split_panel_bg_image_url: string;
  split_welcome_heading: string;
  split_subtitle: string;
}

export interface AuthSettings {
  auth_enabled?: boolean;
  phone?: PhoneChannelSettings;
  email?: EmailChannelSettings;
  password?: PasswordSettings;
  backup_codes?: BackupCodesSettings;
  totp?: TotpSettings;
  passkey?: PasskeySettings;
  captcha?: CaptchaSettings;
  telegram?: TelegramSettings;
  line?: LineSettings;
  woocommerce?: WooCommerceSettings;
  contact_form_7?: ContactForm7Settings;
  mfa_required_roles?: string[];
  enrollment_timing?: EnrollmentTiming;
  grace_period_days?: number;
  auth_base_url?: string;
  redirect_login?: boolean;
  enable_registration?: boolean;
  auto_create_users?: boolean;
  log_verbosity?: LogVerbosity;
  log_retention_days?: number;
  registration_fields?: string[];
  profile_fields?: ProfileFieldDefinition[];
  pending_user_cleanup_enabled?: boolean;
  pending_user_ttl_hours?: number;
  social?: Record<string, SocialProviderSettings>;
  social_profile_sync?: SocialProfileSync;
  site_phone?: string;
  site_phone_channel?: SitePhoneChannel;
  terms_url?: string;
  privacy_url?: string;
  trusted_devices?: TrustedDevicesSettings;
  subscription_consent_text?: string;
  subscription_consent_required?: boolean;
  /** Dynamic add-on channel/settings keys. */
  [key: string]: unknown;
}

export interface TrustedDevicesSettings {
  enabled: boolean;
  ttl: number;
}

export interface BrandingResponse {
  success: boolean;
  settings: BrandingSettings;
  auth_base_url: string;
  auth_pages_active: boolean;
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
    top_failed_ips: { ip: string; count: number; country?: string | null }[];
    recent_lockouts: { user_id: number; display_name: string; locked_at: string; ip: string; country?: string | null }[];
    recent_suspensions: { user_id: number; display_name: string; suspended_at: string; ip: string; country?: string | null }[];
  };
}

export interface LogEntry {
  id: number;
  user_id: number;
  event: string;
  status: string;
  ip_address: string;
  channel_id: string | null;
  geo_country: string | null;
  user_agent: string | null;
  meta: string | Record<string, unknown> | null;
  created_at: string;
  user_display: { display_name: string; email: string } | null;
}

const FALLBACK_CONFIG: Window['wpSmsSettings'] = {
  restUrl: '',
  nonce: '',
  version: '',
  adminUrl: '',
  isPremium: false,
  roles: {},
  timezone: 'UTC',
  area: 'unified',
  phoneInput: { displayMode: 'international' },
  currentUserHasMfa: false,
  currentUserRoles: [],
  capabilities: {
    is_admin: true,
    wsms_view_dashboard: true,
    wsms_view_audience: true,
    wsms_manage_audience: true,
    wsms_view_campaigns: true,
    wsms_manage_campaigns: true,
    wsms_view_automation: true,
    wsms_manage_automation: true,
    wsms_view_channels: true,
    wsms_manage_channels: true,
    wsms_view_identity: true,
    wsms_manage_identity: true,
    wsms_view_monitoring: true,
    wsms_manage_monitoring: true,
    wsms_manage_settings: true,
  },
  hasExtensions: false,
  pluginUrl: '',
  isRtl: false,
  onboarding: null,
  locale: 'en_US',
  detectedIntegrations: [],
  detectedMigrations: [],
};

export function getConfig() {
  return window.wpSmsSettings ?? FALLBACK_CONFIG;
}

function buildApiUrl(restUrl: string, endpoint: string): string {
  const url = new URL(`${restUrl}${endpoint.replace(/^\//, '')}`);
  url.searchParams.set('_locale', 'user');
  return url.toString();
}

async function request<T>(method: string, endpoint: string, body?: unknown, signal?: AbortSignal): Promise<T> {
  const { restUrl, nonce } = getConfig();

  const opts: RequestInit = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-WP-Nonce': nonce,
    },
    credentials: 'same-origin',
    signal,
  };

  if (body) {
    opts.body = JSON.stringify(body);
  }

  const res = await fetch(buildApiUrl(restUrl, endpoint), opts);
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
  const res = await fetch(buildApiUrl(restUrl, endpoint), {
    method: 'POST',
    headers: { 'Accept': 'application/json', 'X-WP-Nonce': nonce },
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
  format?: string;
  enum?: string[];
  enumLabels?: Record<string, string>;
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
  source_ref?: string | null;
  custom_fields: Record<string, unknown>;
  channel_opt_outs?: Record<string, string | null>;
  wp_user_id?: string | null;
  email_verified?: boolean;
  phone_verified?: boolean;
  created_at?: string;
  updated_at?: string;
  tags?: Tag[];
}

export interface SubscriptionFormField {
  key: string;
  required: boolean;
  label: string;
}

export interface SubscriptionFormData {
  id: string;
  name: string;
  slug: string;
  status: string;
  fields: SubscriptionFormField[];
  list_id: string | null;
  tag_id: string | null;
  double_optin: boolean;
  optin_channel: string;
  appearance: Record<string, string>;
  success_message: string;
  redirect_url: string | null;
  consent_text: string | null;
  consent_required: boolean | null;
  privacy_url: string | null;
  created_by: number | null;
  created_at: string | null;
  updated_at: string | null;
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
  dynamic?: boolean;
  depends_on?: string[];
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

export interface TestConnectionResult {
  success: boolean;
  message: string;
  details: Record<string, string>;
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
  description: string;
  group: string;
  icon: string;
  payload_schema: JsonSchema;
  filter_schema: Record<string, JsonSchemaProperty>;
}

export interface ActionDefinition {
  id: string;
  name: string;
  description: string;
  group: string;
  icon: string;
  config_schema: JsonSchema;
  output_schema?: JsonSchema;
  placeholders?: Record<string, Record<string, string>>;
}

export interface PlatformIntegration {
  id: string;
  name: string;
  description: string;
  category: string;
  icon: string;
  available: boolean;
  connected: boolean;
  auth_type: string;
  auth_schema: JsonSchema;
  triggers: number;
  actions: number;
}

export interface IntegrationCapability {
  id: string;
  name: string;
  supported: boolean;
  note?: string | null;
  gateway_id?: string | null;
}

export interface SyncSettings {
  auto_push: boolean;
  push_tags: boolean;
  poll_interval: number;
  poll_enabled: boolean;
  default_list_id: string;
  remove_on_delete: boolean;
}

export interface SyncStatus {
  last_push_at: string | null;
  last_poll_at: string | null;
  total_pushed: number;
  poll_cursor: string | null;
  last_error: string | null;
}

export interface ProviderList {
  id: string;
  name: string;
  contact_count: number;
}

export interface ImportSettings {
  enabled?: boolean;
  field_mapping: Record<string, string>;
  auto_sync: boolean;
  roles?: string[];
  list_id?: string;
}

export interface ImportStats {
  total_synced: number;
  last_synced_at: string | null;
  sync_in_progress: boolean;
  sync_progress: { synced: number } | null;
  last_error: string | null;
}

export interface ImportFields {
  fields: Record<string, { label: string; type: string }>;
  default_mapping: Record<string, string>;
  config_schema: Record<string, unknown>;
}

export interface IntegrationDetail extends Omit<PlatformIntegration, 'triggers' | 'actions'> {
  triggers: Array<{ id: string; name: string; description: string }>;
  actions: Array<{ id: string; name: string; description: string }>;
  config?: Record<string, unknown>;
  capabilities?: IntegrationCapability[];
  sync_settings?: SyncSettings | null;
  sync_status?: SyncStatus | null;
  import_settings?: ImportSettings | null;
  import_stats?: ImportStats | null;
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

export interface WebhookEndpoint {
  id: string;
  label: string;
  url: string;
  created_at: string;
  secret?: string;
}

// --- Outbound Webhook Types ---

export interface OutboundWebhook {
  id: string;
  name: string;
  url: string;
  secret: string;
  events: string[];
  status: 'active' | 'paused';
  description: string | null;
  created_at: string;
  updated_at: string;
  last_test: { success: boolean; message: string; at: string } | null;
}

export interface WebhookTestResult {
  success: boolean;
  message: string;
  details: { status_code?: number; response_time_ms?: number };
}

export interface WebhookEvent {
  name: string;
  label: string;
  description: string;
  sample_payload: Record<string, unknown>;
}

export type WebhookEventGroups = Record<string, WebhookEvent[]>;

export interface ListResponse<T> {
  items: T[];
  total: number;
}

export interface MutationResponse<T> {
  success: boolean;
  data: T;
  error?: string;
  message?: string;
}

// --- Campaign Types ---

export type CampaignStatus = 'draft' | 'scheduled' | 'sending' | 'paused' | 'sent' | 'cancelled' | 'failed';

export interface CampaignAudienceSource {
  type: 'segment' | 'tags' | 'wp_roles' | 'manual';
  conditions?: SegmentConditionGroup;
  tag_ids?: string[];
  roles?: string[];
  recipients?: string[];
}

export interface CampaignAudience {
  sources: CampaignAudienceSource[];
  exclude_unsubscribed?: boolean;
}

export interface CampaignCompliance {
  append_opt_out?: boolean;
  opt_out_text?: string;
}

export interface CampaignRecurrence {
  frequency: 'daily' | 'weekly' | 'monthly';
  end_date?: string;
}

export interface CampaignQuietHours {
  start: string;
  end: string;
  timezone: string;
}

export interface Campaign {
  id: string;
  name: string;
  channel: string;
  gateway_id: string | null;
  status: CampaignStatus;
  subject: string | null;
  body: string;
  media_url?: string;
  audience: CampaignAudience;
  compliance: CampaignCompliance | null;
  send_at: string | null;
  timezone: string;
  recurrence: CampaignRecurrence | null;
  quiet_hours: CampaignQuietHours | null;
  parent_id: string | null;
  total_recipients: number;
  sent_count: number;
  delivered_count: number;
  failed_count: number;
  skipped_count: number;
  total_cost: number;
  last_processed_id: string | null;
  created_by: number | null;
  started_at: string | null;
  completed_at: string | null;
  cancelled_at: string | null;
}

export interface CampaignStats {
  total_recipients: number;
  skipped_count: number;
  total: number;
  delivered: number;
  failed: number;
  sent: number;
  queued: number;
  total_cost: number;
  supports_delivery_receipt: boolean;
}

export interface CampaignRecipient extends MessageLogEntry {
  campaign_id: string;
}

// --- Flow Execution Types ---

export interface StepLog {
  node_id: string;
  type: string;
  status: 'started' | 'completed' | 'failed' | 'retrying';
  input?: Record<string, unknown>;
  output?: Record<string, unknown>;
  error?: string;
  attempt?: number;
  max_attempts?: number;
  at: string;
}

export interface FlowExecution {
  id: string;
  flow_id: string;
  trigger_data: Record<string, unknown>;
  status: 'pending' | 'running' | 'completed' | 'failed' | 'waiting' | 'cancelled';
  step_logs: StepLog[];
  error: string | null;
  started_at: string;
  completed_at: string | null;
}

// --- System Health Types ---

export type HealthCheckStatus = 'pass' | 'warn' | 'fail' | 'skip' | 'info';

export interface HealthCheck {
  id: string;
  label: string;
  status: HealthCheckStatus;
  message: string;
  resolution?: string;
  link?: string;
  details?: Record<string, unknown>;
  lazy?: boolean;
}

export type SectionStatus = 'healthy' | 'warning' | 'critical';

export interface SystemHealthResponse {
  overall_status: {
    level: 'healthy' | 'warnings' | 'critical';
    summary: string;
    section_statuses: Record<string, SectionStatus>;
  };
  checks: {
    messaging: HealthCheck[];
    auth: HealthCheck[];
    background: HealthCheck[];
    configuration: HealthCheck[];
    data_security: HealthCheck[];
  };
  cron_health: {
    wp_cron_disabled: boolean;
    as_runner_active: boolean;
    last_as_run: string | null;
    status: 'healthy' | 'warning' | 'error' | 'unknown';
  };
  heartbeat: {
    hook: string;
    label: string;
    status: 'healthy' | 'late' | 'stopped' | 'unknown';
    last_run: string | null;
    next_run: string | null;
    interval: number;
  }[];
  queue: {
    totals: Record<string, number>;
    by_type: { type: string; pending: number; in_progress: number }[];
    stuck_jobs: { action_id: number; job_type: string; stuck_since: string }[];
  };
  failed_jobs: {
    items: {
      action_id: number;
      job_type: string;
      severity: 'high' | 'low';
      error_message: string;
      failed_at: string;
      attempts: number;
      args_preview: Record<string, string>;
    }[];
    total: number;
    error_groups: { message: string; count: number }[];
  };
  recent_activity: {
    action_id: number;
    hook: string;
    job_type: string;
    status: string;
    completed_at: string;
  }[];
  active_campaigns: {
    id: string;
    name: string;
    channel: string;
    status: string;
    total_recipients: number;
    sent_count: number;
    delivered_count: number;
    failed_count: number;
    started_at: string;
  }[];
  generated_at: string;
}

// --- Dashboard Types ---

export interface ActiveCampaignSummary {
  id: string;
  name: string;
  channel: string;
  status: string;
  total_recipients: number;
  sent_count: number;
  delivered_count: number;
  failed_count: number;
}

export interface ChannelBreakdown {
  channel: string;
  sent: number;
  successful: number;
  failed: number;
  success_rate: number | null;
  cost: number;
}

export interface DashboardGateway {
  id: string;
  name: string;
  channels: string[];
  healthy: boolean;
}

export interface DashboardSummaryResponse {
  range: number;
  feature_state: {
    auth_enabled: boolean;
    gateways_configured: boolean;
    delivery_tracking_available: boolean;
    has_contacts: boolean;
    has_campaigns: boolean;
  };
  messaging: {
    messages_sent: number;
    messages_sent_delta: number | null;
    success_rate: number | null;
    success_rate_insight: string | null;
    success_rate_status: 'success' | 'warning' | 'destructive' | null;
    messages_failed: number;
    total_cost: number;
    active_campaigns: number;
    completed_campaigns: number;
    top_active_campaigns: ActiveCampaignSummary[];
  };
  channels: ChannelBreakdown[];
  contacts: {
    total: number;
    subscribed: number;
    new_in_period: number;
    growth_delta: number | null;
  };
  flows: {
    active: number;
    paused: number;
    draft: number;
    executions_completed: number;
    executions_failed: number;
  };
  gateways: DashboardGateway[];
  auth: {
    enabled: boolean;
    total_logins?: number;
    login_success_rate?: number | null;
    login_success_status?: 'success' | 'warning' | 'destructive' | null;
    registrations?: number;
    failed_logins?: number;
    accounts_locked?: number;
    accounts_suspended?: number;
  };
  system_health: {
    level: 'healthy' | 'warnings' | 'critical';
    summary: string;
    section_statuses: Record<string, string>;
    top_issue: {
      section: string;
      label: string;
      status: string;
      description: string;
    } | null;
  };
  generated_at: string;
}

export type GatewayCreditsResponse = Record<string, {
  gateway_id: string;
  name: string;
  credit: string | null;
  error?: string;
}>;

export interface TableSizeEntry {
  table: string;
  rows: number;
  data_size_mb: number;
  index_size_mb: number;
}
