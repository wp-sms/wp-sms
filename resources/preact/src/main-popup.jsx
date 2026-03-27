import { render } from 'preact';
import { App, popupOpen, popupView } from './App';
import { EmbedApp } from './EmbedApp';
import { BrandingProvider } from './components/BrandingProvider';
import { authConfig, formSlug, loadConfig, renderMode } from './signals/config';
import { applyBrandingVars } from './utils/apply-branding';
import popupStyles from './styles/auth-shadow.css?inline';

// Apply branding CSS vars on document.documentElement BEFORE shadow DOM
// CSS custom properties inherit through shadow DOM boundaries
applyBrandingVars(window.wsmsAuth?.branding);

let mounted = false;
let hostEl = null;

function ensureMounted() {
    if (mounted) return;
    mounted = true;

    // Create Shadow DOM host for CSS isolation
    hostEl = document.createElement('div');
    hostEl.id = 'wsms-auth-popup-host';
    document.body.appendChild(hostEl);

    const shadow = hostEl.attachShadow({ mode: 'open' });

    // Inject styles into shadow DOM
    const styleEl = document.createElement('style');
    styleEl.textContent = popupStyles;
    shadow.appendChild(styleEl);

    // Mount container inside shadow DOM
    const mountEl = document.createElement('div');
    mountEl.id = 'wsms-auth-popup';
    shadow.appendChild(mountEl);

    render(<BrandingProvider><App mode="popup" /></BrandingProvider>, mountEl);
}

// Callback registry for auth events
const authCallbacks = [];

/**
 * Global API: wsmsAuth.open(view), wsmsAuth.close(), wsmsAuth.onAuth(cb)
 */
const popupApi = {
    open(view = 'login') {
        ensureMounted();
        renderMode.value = 'popup';
        popupView.value = view;
        popupOpen.value = true;
    },

    close() {
        popupOpen.value = false;
    },

    onAuth(callback) {
        if (typeof callback === 'function') {
            authCallbacks.push(callback);
        }
        // Return unsubscribe function
        return () => {
            const idx = authCallbacks.indexOf(callback);
            if (idx !== -1) authCallbacks.splice(idx, 1);
        };
    },

    _notifyAuth(user) {
        authCallbacks.forEach((cb) => cb(user));
    },
};

// Merge popup API into existing wsmsAuth global (preserving restUrl, nonce, etc.)
if (typeof window !== 'undefined') {
    window.wsmsAuth = Object.assign(window.wsmsAuth || {}, popupApi);
}

// Mount a single embed container into Shadow DOM
function mountEmbed(container) {
    if (container.shadowRoot) return; // already mounted

    const view = container.dataset.wsmsEmbedView || 'register';
    const embedFormSlug = container.dataset.wsmsEmbedForm || null;

    const shadow = container.attachShadow({ mode: 'open' });

    const styleEl = document.createElement('style');
    styleEl.textContent = popupStyles;
    shadow.appendChild(styleEl);

    const mountEl = document.createElement('div');
    mountEl.id = 'wsms-auth-embed';
    shadow.appendChild(mountEl);

    render(<EmbedApp view={view} formSlug={embedFormSlug} />, mountEl);
}

// Mount embeds already in DOM
document.querySelectorAll('[data-wsms-embed-view]').forEach(mountEmbed);

// Watch for dynamically added embeds (page builders, AJAX, tabs/accordions)
new MutationObserver((mutations) => {
    for (const { addedNodes } of mutations) {
        for (const node of addedNodes) {
            if (node.nodeType !== 1) continue;
            if (node.matches?.('[data-wsms-embed-view]')) mountEmbed(node);
            node.querySelectorAll?.('[data-wsms-embed-view]').forEach(mountEmbed);
        }
    }
}).observe(document.body, { childList: true, subtree: true });

// Event delegation: clicks on [data-wsms-auth-view] elements open the popup
document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-wsms-auth-view]');
    if (trigger) {
        e.preventDefault();
        const view = trigger.dataset.wsmsAuthView || 'login';
        const fSlug = trigger.dataset.wsmsFormId || null;

        if (fSlug !== formSlug.value || !authConfig.value) {
            formSlug.value = fSlug;
            authConfig.value = null;
            loadConfig(fSlug, { force: true });
        }

        popupApi.open(view);
    }
});
