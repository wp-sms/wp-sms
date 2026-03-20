import { useResourceSettings, type SaveStatus } from '@/hooks/use-resource-settings';

export type { SaveStatus };

export interface MessagingButtonSettings extends Record<string, unknown> {
  enabled: boolean;
  button: {
    position: 'bottom-right' | 'bottom-left';
    style: 'icon-text' | 'icon' | 'text';
    text: string;
    primary_color: string;
    text_color: string;
    attention: 'none' | 'pulse' | 'bounce' | 'badge';
  };
  widget: {
    title: string;
    subtitle: string;
    theme: 'light' | 'dark' | 'system';
  };
  pages: {
    welcome: {
      enabled: boolean;
      greeting: string;
      cta_label: string;
    };
    contact_form: {
      enabled: boolean;
      fields: string[];
      required_fields: string[];
      channel: 'email' | 'sms';
      gateway_id: string | null;
      notification_recipients: string[];
      auto_tag: string | null;
      auto_list: string | null;
    };
    team: { enabled: boolean };
    resources: {
      enabled: boolean;
      links: Array<{ title: string; url: string; description: string }>;
    };
  };
  team_members: Array<{
    name: string;
    role: string;
    avatar_url: string;
    contact_methods: Array<{ type: string; value: string }>;
  }>;
  display_rules: {
    auto_inject: boolean;
    include_urls: string[];
    exclude_urls: string[];
    visibility: 'everyone' | 'logged_in' | 'logged_out';
  };
  greeting_bubble: {
    enabled: boolean;
    message: string;
    delay: number;
    duration: number;
    open_on_click: boolean;
  };
  default_message: string;
  triggers: {
    auto_open_delay: number;
    scroll_percent: number;
    exit_intent: boolean;
  };
  business_hours: {
    enabled: boolean;
    schedule: Array<{ day: string; open: string; close: string }>;
    offline_message: string;
  };
  gdpr: {
    enabled: boolean;
    consent_text: string;
    link_url: string;
  };
}

const DEFAULTS: MessagingButtonSettings = {
  enabled: false,
  button: {
    position: 'bottom-right',
    style: 'icon-text',
    text: 'Chat with us',
    primary_color: '#2563eb',
    text_color: '#ffffff',
    attention: 'none',
  },
  widget: {
    title: 'Hi there!',
    subtitle: 'How can we help?',
    theme: 'light',
  },
  pages: {
    welcome: { enabled: true, greeting: 'Welcome! Choose an option below to get started.', cta_label: 'Send a message' },
    contact_form: { enabled: true, fields: ['name', 'email', 'phone', 'message'], required_fields: ['email', 'message'], channel: 'email', gateway_id: null, notification_recipients: [], auto_tag: null, auto_list: null },
    team: { enabled: true },
    resources: { enabled: false, links: [] },
  },
  team_members: [],
  display_rules: { auto_inject: true, include_urls: [], exclude_urls: [], visibility: 'everyone' },
  greeting_bubble: { enabled: false, message: 'Need help? We\'re online!', delay: 3, duration: 8, open_on_click: true },
  default_message: '',
  triggers: { auto_open_delay: 0, scroll_percent: 0, exit_intent: false },
  business_hours: { enabled: false, schedule: [], offline_message: 'We are currently offline.' },
  gdpr: { enabled: false, consent_text: 'I agree to the privacy policy.', link_url: '' },
};

interface MBSettingsResponse {
  success: boolean;
  settings: MessagingButtonSettings;
  wp_timezone?: string;
}

export function useMessagingButtonSettings() {
  const result = useResourceSettings<MessagingButtonSettings, MBSettingsResponse>({
    endpoint: 'messaging-button/settings',
    defaults: DEFAULTS,
    extractSettings: (res) => res.settings,
  });

  return {
    settings: result.settings,
    updateSettings: result.updateSetting,
    isDirty: result.isDirty,
    saveStatus: result.saveStatus,
    save: result.save,
    loading: result.loading,
    error: result.error,
    rawResponse: result.rawResponse,
  };
}
