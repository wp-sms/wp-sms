import { render } from 'preact';
import { UserButtonDropdown } from './UserButtonDropdown';
import styles from './styles/user-button.css?inline';

const config = window.wsmsUserButtonConfig || {};

function mountWidget(container) {
    if (!config.user) return;

    const shadow = container.attachShadow({ mode: 'open' });

    const styleEl = document.createElement('style');
    styleEl.textContent = styles;
    shadow.appendChild(styleEl);

    const mountEl = document.createElement('div');
    shadow.appendChild(mountEl);

    render(
        <UserButtonDropdown
            user={config.user}
            baseUrl={config.baseUrl || '/account'}
            logoutUrl={config.logoutUrl || '/wp-login.php?action=logout'}
        />,
        mountEl,
    );
}

// Auto-mount to all [data-wsms-user-button] elements
function autoMount() {
    document.querySelectorAll('[data-wsms-user-button]').forEach((el) => {
        if (!el.shadowRoot) mountWidget(el);
    });
}

// Public API
window.wsmsUserButton = {
    mount(el) {
        if (el && !el.shadowRoot) mountWidget(el);
    },
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoMount);
} else {
    autoMount();
}
