import { render } from 'preact';
import { signal } from '@preact/signals';
import { MessagingButtonApp } from './MessagingButtonApp';
import { generateMbPalette } from './utils/mb-palette';
import styles from './styles/messaging-button.css?inline';

let mounted = false;
let hostEl = null;

// Global state signals
export const widgetOpen = signal(false);
export const currentPage = signal('welcome');

/**
 * Apply palette CSS variables to the shadow host.
 * For 'system' theme, injects a <style> with both light and dark rules
 * inside the shadow DOM so the media query can properly switch modes.
 * For single modes (light/dark), applies vars inline on the host element.
 */
const DARK_SHADOW = '0 5px 40px rgba(0, 0, 0, 0.4)';

function varsToCSS(vars) {
    return Object.entries(vars).map(([k, v]) => `  ${k}: ${v};`).join('\n');
}

function applyPalette(host, shadow, primaryColor, theme) {
    const palette = generateMbPalette(primaryColor, theme);

    if (palette.light && palette.dark) {
        // Auto/system mode: use a stylesheet so the media query can override
        // (inline styles would prevent media queries from switching)
        let paletteStyle = shadow.getElementById('wsms-mb-palette');
        if (!paletteStyle) {
            paletteStyle = document.createElement('style');
            paletteStyle.id = 'wsms-mb-palette';
            shadow.appendChild(paletteStyle);
        }
        paletteStyle.textContent =
            `:host {\n${varsToCSS(palette.light)}\n}\n` +
            `@media (prefers-color-scheme: dark) {\n  :host {\n${varsToCSS(palette.dark)}\n    --mb-shadow: ${DARK_SHADOW};\n  }\n}`;
    } else {
        // Single mode: inline styles are fine (no mode switching needed)
        for (const [key, value] of Object.entries(palette)) {
            host.style.setProperty(key, value);
        }
        if (theme === 'dark') {
            host.style.setProperty('--mb-shadow', DARK_SHADOW);
        }
    }
}

function ensureMounted() {
    if (mounted) return;
    mounted = true;

    hostEl = document.createElement('div');
    hostEl.id = 'wsms-messaging-button-host';
    document.body.appendChild(hostEl);

    const shadow = hostEl.attachShadow({ mode: 'open' });

    const styleEl = document.createElement('style');
    styleEl.textContent = styles;
    shadow.appendChild(styleEl);

    // Apply palette-derived CSS variables
    const config = window.wsmsMessagingButtonConfig?.config || {};
    const primaryColor = config.button?.primary_color || '#2563eb';
    const theme = config.widget?.theme || 'light';
    applyPalette(hostEl, shadow, primaryColor, theme);

    const mountEl = document.createElement('div');
    mountEl.id = 'wsms-messaging-button';
    shadow.appendChild(mountEl);

    render(<MessagingButtonApp />, mountEl);
}

const widgetApi = {
    open(page = 'welcome') {
        ensureMounted();
        currentPage.value = page;
        widgetOpen.value = true;
    },
    close() {
        widgetOpen.value = false;
    },
    toggle() {
        if (widgetOpen.value) {
            widgetOpen.value = false;
        } else {
            ensureMounted();
            widgetOpen.value = true;
        }
    },
};

if (typeof window !== 'undefined') {
    window.wsmsMessagingButton = Object.assign(window.wsmsMessagingButton || {}, widgetApi);
}

// Auto-mount on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ensureMounted);
} else {
    ensureMounted();
}

// Event delegation for trigger elements
document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-wsms-chat]');
    if (trigger) {
        e.preventDefault();
        const page = trigger.dataset.wsmsChat || 'welcome';
        widgetApi.open(page);
    }
});
