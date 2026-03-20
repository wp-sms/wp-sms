import { signal, computed } from '@preact/signals';
import { generatePalette } from '../utils/color';

/** Raw branding config from window.wsmsAuth.branding or postMessage overrides. */
export const brandingConfig = signal(window.wsmsAuth?.branding ?? null);

/** Whether the page is in preview mode (?preview=1 in URL). */
export const isPreviewMode = signal(
    typeof location !== 'undefined' && new URLSearchParams(location.search).has('preview')
);

/** Computed CSS variable map derived from current branding config. */
export const cssPalette = computed(() => {
    const config = brandingConfig.value;
    if (!config) return {};
    return generatePalette(config);
});
