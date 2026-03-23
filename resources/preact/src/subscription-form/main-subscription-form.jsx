import { render } from 'preact';
import { SubscriptionFormApp } from './SubscriptionFormApp';
import './styles/subscription-form.css';

function mountForms() {
    const mountPoints = document.querySelectorAll('[data-wsms-sub-form]');
    const configs = window.wsmsSubscriptionForms || {};

    mountPoints.forEach((el) => {
        // Skip if already mounted.
        if (el.dataset.wsmsMounted) return;

        const slug = el.dataset.wsmsSubForm;
        const config = configs[slug];

        if (!config) return;

        // Add slug to config for API calls.
        config.slug = slug;

        // Apply branding color as CSS variable.
        if (config.primaryColor) {
            el.style.setProperty('--wsms-sub-primary', config.primaryColor);
        }

        el.dataset.wsmsMounted = '1';
        render(<SubscriptionFormApp config={config} />, el);
    });
}

// Mount on DOM ready.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountForms);
} else {
    mountForms();
}
