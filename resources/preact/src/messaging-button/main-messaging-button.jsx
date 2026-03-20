import { render } from 'preact';
import { signal } from '@preact/signals';
import { MessagingButtonApp } from './MessagingButtonApp';
import styles from './styles/messaging-button.css?inline';

let mounted = false;
let hostEl = null;

// Global state signals
export const widgetOpen = signal(false);
export const currentPage = signal('welcome');

function ensureMounted() {
    if (mounted) return;
    mounted = true;

    hostEl = document.createElement('div');
    hostEl.id = 'wsms-messaging-button-host';
    document.body.appendChild(hostEl);

    const shadow = hostEl.attachShadow({ mode: 'open' });

    // Apply theme class to the shadow host for CSS variable overrides
    const theme = window.wsmsMessagingButtonConfig?.config?.widget?.theme || 'light';
    if (theme === 'dark' || theme === 'system') {
        hostEl.classList.add(`wsms-mb-${theme}`);
    }

    const styleEl = document.createElement('style');
    styleEl.textContent = styles;
    shadow.appendChild(styleEl);

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
