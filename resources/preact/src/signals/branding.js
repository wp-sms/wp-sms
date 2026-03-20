import { signal, computed } from '@preact/signals';
import { generatePalette } from '../utils/color';

const PREVIEW_BRANDING_KEY = 'wsms-preview-branding';

/** Whether the page is in preview mode (?preview=1 in URL). */
export const isPreviewMode = signal(
    typeof location !== 'undefined' && new URLSearchParams(location.search).has('preview')
);

/** Resolve initial branding: prefer sessionStorage in standalone preview tabs. */
function resolveInitialBranding() {
    if (isPreviewMode.peek() && window.parent === window) {
        try {
            const stored = sessionStorage.getItem(PREVIEW_BRANDING_KEY);
            if (stored) return JSON.parse(stored);
        } catch { /* fall through to database branding */ }
    }
    return window.wsmsAuth?.branding ?? null;
}

/** Raw branding config from resolved source or postMessage overrides. */
export const brandingConfig = signal(resolveInitialBranding());

/** Computed CSS variable map derived from current branding config. */
export const cssPalette = computed(() => {
    const config = brandingConfig.value;
    if (!config) return {};
    return generatePalette(config);
});
