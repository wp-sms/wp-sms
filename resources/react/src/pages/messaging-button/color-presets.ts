import { COLOR_PRESETS } from '../branding/color-presets';
import type { MessagingButtonSettings } from './use-mb-settings';

export interface MbColorPreset {
  id: string;
  name: string;
  primary_color: string;
  text_color: string;
}

/**
 * Derive messaging button presets from the branding presets.
 * Each uses the branding preset's primary_color; text_color is white
 * (good contrast on all these primaries as button text).
 */
export const MB_COLOR_PRESETS: MbColorPreset[] = COLOR_PRESETS.map((p) => ({
  id: p.id,
  name: p.name,
  primary_color: p.primary_color,
  text_color: '#ffffff',
}));

/**
 * Detect which preset matches the current button colors (if any).
 * Returns the preset id, or 'custom' if none match.
 */
export function getActivePresetId(settings: MessagingButtonSettings): string {
  const { primary_color, text_color } = settings.button;
  for (const preset of MB_COLOR_PRESETS) {
    if (
      preset.primary_color === primary_color &&
      preset.text_color === text_color
    ) {
      return preset.id;
    }
  }
  return 'custom';
}
