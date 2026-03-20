import { generatePalette } from './color';

/**
 * Apply branding CSS variables to document.documentElement synchronously.
 * Used at module level in entry points to prevent FOUC.
 */
export function applyBrandingVars(branding) {
    if (!branding) return;
    const vars = generatePalette(branding);
    const root = document.documentElement;
    for (const [key, value] of Object.entries(vars)) {
        root.style.setProperty(key, value);
    }
}
