import { __ } from '@wordpress/i18n';
import type { BrandingSettings } from '@/lib/api';

export interface ColorPreset {
  id: string;
  name: string;
  primary_color: string;
  accent_color: string;
  text_color: string;
  error_color: string;
  background_color: string;
  split_panel_bg_color: string;
}

export const COLOR_PRESETS: ColorPreset[] = [
  {
    id: 'clean',
    name: __('Clean', 'wp-sms'),
    primary_color: '#171717',
    accent_color: '#6366f1',
    text_color: '#1c1917',
    error_color: '#dc2626',
    background_color: '#ffffff',
    split_panel_bg_color: '#171717',
  },
  {
    id: 'slate',
    name: __('Slate', 'wp-sms'),
    primary_color: '#475569',
    accent_color: '#6366f1',
    text_color: '#1e293b',
    error_color: '#dc2626',
    background_color: '#f8fafc',
    split_panel_bg_color: '#1e293b',
  },
  {
    id: 'ocean',
    name: __('Ocean', 'wp-sms'),
    primary_color: '#0284c7',
    accent_color: '#06b6d4',
    text_color: '#0c4a6e',
    error_color: '#dc2626',
    background_color: '#f0f9ff',
    split_panel_bg_color: '#0c4a6e',
  },
  {
    id: 'indigo',
    name: __('Indigo', 'wp-sms'),
    primary_color: '#4f46e5',
    accent_color: '#6366f1',
    text_color: '#312e81',
    error_color: '#dc2626',
    background_color: '#eef2ff',
    split_panel_bg_color: '#312e81',
  },
  {
    id: 'nordic',
    name: __('Nordic', 'wp-sms'),
    primary_color: '#2563eb',
    accent_color: '#818cf8',
    text_color: '#1e293b',
    error_color: '#dc2626',
    background_color: '#f1f5f9',
    split_panel_bg_color: '#0f172a',
  },
  {
    id: 'amber',
    name: __('Amber', 'wp-sms'),
    primary_color: '#d97706',
    accent_color: '#f59e0b',
    text_color: '#78350f',
    error_color: '#dc2626',
    background_color: '#fffbeb',
    split_panel_bg_color: '#78350f',
  },
  {
    id: 'mocha',
    name: __('Mocha', 'wp-sms'),
    primary_color: '#8b5320',
    accent_color: '#a3743e',
    text_color: '#3c1f08',
    error_color: '#dc2626',
    background_color: '#fdf8f0',
    split_panel_bg_color: '#3c1f08',
  },
  {
    id: 'midnight',
    name: __('Midnight', 'wp-sms'),
    primary_color: '#818cf8',
    accent_color: '#a78bfa',
    text_color: '#e2e8f0',
    error_color: '#f87171',
    background_color: '#0f172a',
    split_panel_bg_color: '#1e293b',
  },
  {
    id: 'volcano',
    name: __('Volcano', 'wp-sms'),
    primary_color: '#f97316',
    accent_color: '#ef4444',
    text_color: '#fed7aa',
    error_color: '#f87171',
    background_color: '#1c1210',
    split_panel_bg_color: '#292018',
  },
];

const PRESET_COLOR_KEYS: (keyof ColorPreset)[] = [
  'primary_color',
  'accent_color',
  'text_color',
  'error_color',
  'background_color',
  'split_panel_bg_color',
];

/**
 * Detect which preset matches the current branding (if any).
 * Returns the preset id, or 'custom' if none match.
 */
export function getActivePresetId(branding: BrandingSettings): string {
  for (const preset of COLOR_PRESETS) {
    const matches = PRESET_COLOR_KEYS.every(
      (key) => (branding as Record<string, unknown>)[key] === preset[key]
    );
    if (matches) return preset.id;
  }
  return 'custom';
}
