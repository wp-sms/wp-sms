import { useResourceSettings, type SaveStatus } from '@/hooks/use-resource-settings';
import type { BrandingSettings, BrandingResponse } from '@/lib/api';

export type { SaveStatus };

export const BRANDING_DEFAULTS: Record<string, unknown> & BrandingSettings = {
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
};

type BrandingRecord = Record<string, unknown> & BrandingSettings;

export function useBrandingSettings() {
  const result = useResourceSettings<BrandingRecord, BrandingResponse>({
    endpoint: 'branding/settings',
    defaults: BRANDING_DEFAULTS,
    extractSettings: (res) => res.settings,
  });

  return {
    settings: result.settings as BrandingSettings,
    updateSetting: result.updateSetting,
    isDirty: result.isDirty,
    saveStatus: result.saveStatus,
    save: result.save,
    loading: result.loading,
    error: result.error,
    authBaseUrl: result.rawResponse?.auth_base_url ?? '/account',
    authPagesActive: result.rawResponse?.auth_pages_active ?? false,
  };
}
