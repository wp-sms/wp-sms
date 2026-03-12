import { render } from 'preact';
import { App, popupOpen, popupView } from './App';
import popupStyles from './styles/auth.css?inline';

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

    render(<App mode="popup" />, mountEl);
}

// Callback registry for auth events
const authCallbacks = [];

/**
 * Global API: wsmsAuth.open(view), wsmsAuth.close(), wsmsAuth.onAuth(cb)
 */
const popupApi = {
    open(view = 'login') {
        ensureMounted();
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

// Event delegation: clicks on [data-wsms-auth-view] elements open the popup
document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-wsms-auth-view]');
    if (trigger) {
        e.preventDefault();
        const view = trigger.dataset.wsmsAuthView || 'login';
        popupApi.open(view);
    }
});
