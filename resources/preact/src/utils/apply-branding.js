import { generatePalette } from './color';
import { isAutoModePalette, flattenLightPalette, injectDarkModeSheet, applyVarsToRoot } from './branding-dom';

/**
 * Apply branding CSS variables to document.documentElement synchronously.
 * Used at module level in entry points to prevent FOUC.
 */
export function applyBrandingVars(branding) {
    if (!branding) return;
    const palette = generatePalette(branding);
    const root = document.documentElement;

    if (isAutoModePalette(palette)) {
        applyVarsToRoot(flattenLightPalette(palette), root);
        injectDarkModeSheet(palette.dark);
    } else {
        applyVarsToRoot(palette, root);
    }

    root.dataset.btnStyle = branding.button_style || 'filled';
}
