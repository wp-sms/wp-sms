/**
 * Shared DOM helpers for applying branding CSS variables.
 * Used by both apply-branding.js (synchronous FOUC prevention)
 * and BrandingProvider.jsx (reactive updates).
 */

export const AUTO_DARK_STYLE_ID = 'wsms-auto-dark';

/**
 * Check if a palette object is auto-mode (has light/dark sub-objects).
 */
export function isAutoModePalette(palette) {
    return palette && palette.light && palette.dark;
}

/**
 * Merge light and shared vars into a single flat map.
 */
export function flattenLightPalette(palette) {
    return { ...palette.light, ...(palette.shared || {}) };
}

/**
 * Create or update the dark-mode media query <style> element.
 */
export function injectDarkModeSheet(darkVars) {
    let styleEl = document.getElementById(AUTO_DARK_STYLE_ID);
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = AUTO_DARK_STYLE_ID;
        document.head.appendChild(styleEl);
    }
    const lines = Object.entries(darkVars)
        .map(([k, v]) => `  ${k}: ${v};`)
        .join('\n');
    styleEl.textContent = `@media (prefers-color-scheme: dark) {\n  :root {\n${lines}\n  }\n}`;
}

/**
 * Remove the dark-mode media query <style> element if it exists.
 */
export function removeDarkModeSheet() {
    const el = document.getElementById(AUTO_DARK_STYLE_ID);
    if (el) el.remove();
}

/**
 * Apply a flat CSS variable map to :root inline styles.
 * Tracks applied keys via appliedKeysRef for cleanup on next call.
 */
export function applyVarsToRoot(vars, root, appliedKeysRef) {
    const newKeys = Object.keys(vars);

    // Remove vars that were in the old set but not the new one
    if (appliedKeysRef) {
        for (const key of appliedKeysRef.current) {
            if (!(key in vars)) {
                root.style.removeProperty(key);
            }
        }
    }

    for (const [key, value] of Object.entries(vars)) {
        root.style.setProperty(key, value);
    }

    if (appliedKeysRef) {
        appliedKeysRef.current = newKeys;
    }
}
