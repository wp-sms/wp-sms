import { render } from 'preact';
import { App, popupOpen, popupView } from './App';
import { EmbedApp } from './EmbedApp';
import { BrandingProvider } from './components/BrandingProvider';
import { authConfig, formSlug, loadConfig, renderMode } from './signals/config';
import { applyBrandingVars } from './utils/apply-branding';
import { brandingConfig } from './signals/branding';
import './styles/auth.css';

// document.currentScript is null inside async callbacks — capture at parse time
const scriptSrc = document.currentScript?.src || '';
const cssBase = scriptSrc.substring(0, scriptSrc.lastIndexOf('/') + 1);
const verQuery = scriptSrc.includes('?') ? scriptSrc.substring(scriptSrc.indexOf('?')) : '';

function createShadowMount(host, mountId) {
    const shadow = host.attachShadow({ mode: 'open' });

    const vendorLink = document.createElement('link');
    vendorLink.rel = 'stylesheet';
    vendorLink.href = cssBase + 'vendor.css' + verQuery;

    const styleLink = document.createElement('link');
    styleLink.rel = 'stylesheet';
    styleLink.href = cssBase + 'style.css' + verQuery;

    shadow.appendChild(vendorLink);
    shadow.appendChild(styleLink);

    const mountEl = document.createElement('div');
    mountEl.id = mountId;
    mountEl.dir = document.documentElement.dir || 'ltr';
    // Reset inherited typography so host page styles don't leak into shadow DOM.
    mountEl.style.cssText = 'font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.5; color: oklch(0.147 0.004 49.25);';
    shadow.appendChild(mountEl);

    const ready = Promise.all([
        new Promise(r => { vendorLink.onload = r; vendorLink.onerror = r; }),
        new Promise(r => { styleLink.onload = r; styleLink.onerror = r; }),
    ]);

    return { shadow, mountEl, ready };
}

const fullpageEl = document.getElementById('wsms-auth');

// Only apply branding vars to :root on fullpage — Shadow DOM pages handle it internally
if (fullpageEl) {
    applyBrandingVars(brandingConfig.value);
    render(
        <BrandingProvider><App /></BrandingProvider>,
        fullpageEl,
    );
}

const authCallbacks = [];
let popupMounted = false;

function ensurePopupMounted() {
    if (popupMounted) return;
    popupMounted = true;

    const hostEl = document.createElement('div');
    hostEl.id = 'wsms-auth-popup-host';
    document.body.appendChild(hostEl);

    const { mountEl, ready } = createShadowMount(hostEl, 'wsms-auth-popup');
    ready.then(() => {
        render(<BrandingProvider><App mode="popup" /></BrandingProvider>, mountEl);
    });
}

const popupApi = {
    open(view = 'login') {
        ensurePopupMounted();
        renderMode.value = 'popup';
        popupView.value = view;
        popupOpen.value = true;
    },
    close() { popupOpen.value = false; },
    onAuth(callback) {
        if (typeof callback === 'function') authCallbacks.push(callback);
        return () => {
            const idx = authCallbacks.indexOf(callback);
            if (idx !== -1) authCallbacks.splice(idx, 1);
        };
    },
    _notifyAuth(user) { authCallbacks.forEach(cb => cb(user)); },
};

window.wsmsAuth = Object.assign(window.wsmsAuth || {}, popupApi);

if (!fullpageEl) {
    function mountEmbed(container) {
        if (container.shadowRoot) return;
        const view = container.dataset.wsmsEmbedView || 'register';
        const embedFormSlug = container.dataset.wsmsEmbedForm || null;
        const { mountEl, ready } = createShadowMount(container, 'wsms-auth-embed');
        ready.then(() => {
            render(<EmbedApp view={view} formSlug={embedFormSlug} />, mountEl);
        });
    }

    document.querySelectorAll('[data-wsms-embed-view]').forEach(mountEmbed);

    new MutationObserver((mutations) => {
        for (const { addedNodes } of mutations) {
            for (const node of addedNodes) {
                if (node.nodeType !== 1) continue;
                if (node.matches?.('[data-wsms-embed-view]')) mountEmbed(node);
                node.querySelectorAll?.('[data-wsms-embed-view]').forEach(mountEmbed);
            }
        }
    }).observe(document.body, { childList: true, subtree: true });

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-wsms-auth-view]');
        if (!trigger) return;
        e.preventDefault();
        const view = trigger.dataset.wsmsAuthView || 'login';
        const fSlug = trigger.dataset.wsmsFormId || null;
        if (fSlug !== formSlug.value || !authConfig.value) {
            formSlug.value = fSlug;
            authConfig.value = null;
            loadConfig(fSlug, { force: true });
        }
        popupApi.open(view);
    });
}
