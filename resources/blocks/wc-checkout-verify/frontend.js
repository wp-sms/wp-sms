import { registerPlugin } from '@wordpress/plugins';
import { createPortal, useState, useEffect } from '@wordpress/element';
import { WsmsCheckoutVerify } from './components/WsmsCheckoutVerify.js';

/**
 * Renders WsmsCheckoutVerify into the portal target injected by PHP
 * after the contact information block. Falls back to inline rendering
 * if the portal target doesn't exist.
 */
function WsmsCheckoutVerifyPortal() {
    const [portalTarget, setPortalTarget] = useState(null);

    useEffect(() => {
        const el = document.getElementById('wsms-checkout-verify-slot');
        if (el) setPortalTarget(el);
    }, []);

    const content = wp.element.createElement(WsmsCheckoutVerify, null);

    if (portalTarget) {
        return createPortal(content, portalTarget);
    }

    // Fallback: render inline (will appear wherever the plugin renders).
    return content;
}

registerPlugin('wsms-checkout-verify', {
    render: () => wp.element.createElement(WsmsCheckoutVerifyPortal, null),
    scope: 'woocommerce-checkout',
});
