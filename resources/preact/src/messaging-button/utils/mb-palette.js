/**
 * Adapter: maps generatePalette() output to --mb-* CSS variables.
 *
 * The branding palette generator (OKLch-based) derives a full design system
 * from seed colors. This module bridges those outputs to the messaging button's
 * variable namespace so colors adapt to the user's chosen primary color.
 */
import { generatePalette } from '../../utils/color';

/**
 * Map branding palette vars to messaging button vars.
 */
function mapVars(vars) {
    return {
        '--mb-accent': vars['--primary'],
        '--mb-text': vars['--foreground'],
        '--mb-text-secondary': vars['--muted-foreground'],
        '--mb-bg': vars['--background'],
        '--mb-bg-secondary': vars['--card'],
        '--mb-border': vars['--border'],
    };
}

/**
 * Generate --mb-* CSS variables from a primary color and theme.
 *
 * @param {string} primaryColor - Hex color (e.g. '#2563eb')
 * @param {'light'|'dark'|'system'} theme - Widget theme setting
 * @returns For light/dark: flat Record<string, string>.
 *          For system: { light: Record, dark: Record }.
 */
export function generateMbPalette(primaryColor, theme) {
    const colorMode = theme === 'system' ? 'auto' : theme;

    const config = {
        primary_color: primaryColor,
        accent_color: primaryColor,
        text_color: colorMode === 'dark' ? '#e2e8f0' : '#1c1917',
        background_color: colorMode === 'dark' ? '#0f172a' : '#ffffff',
        color_mode: colorMode,
    };

    const palette = generatePalette(config);

    if (palette.light && palette.dark) {
        return {
            light: mapVars(palette.light),
            dark: mapVars(palette.dark),
        };
    }

    return mapVars(palette);
}
