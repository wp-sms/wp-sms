import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { getSetting } from '@woocommerce/settings';

const STATUS = { idle: 0, sending: 1, input: 2, verifying: 3, verified: 4, error: 5 };

const INFO_ICON = 'M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.3-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.2 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM11 17h2v-6h-2v6zm0-8h2V7h-2v2z';
const CHECK_ICON = 'M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z';

function Icon({ path }) {
    return wp.element.createElement('svg', {
        xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24',
        width: '24', height: '24', 'aria-hidden': 'true', focusable: 'false',
    }, wp.element.createElement('path', { d: path }));
}

function NoticeBanner({ type, children }) {
    return wp.element.createElement('div', {
        className: `wc-block-components-notice-banner is-${type}`,
        role: type === 'error' ? 'alert' : 'status',
    },
        wp.element.createElement(Icon, { path: type === 'success' ? CHECK_ICON : INFO_ICON }),
        wp.element.createElement('div', { className: 'wc-block-components-notice-banner__content' }, children),
    );
}

/**
 * Native React verification component styled with WooCommerce CSS classes.
 * Zero inline styles — uses only WC's own class system.
 */
export function VerifyBridge({ channel, identifier, onVerified }) {
    const [status, setStatus] = useState(STATUS.idle);
    const [sessionToken, setSessionToken] = useState('');
    const [errorMsg, setErrorMsg] = useState('');
    const [cooldown, setCooldown] = useState(0);
    const [otp, setOtp] = useState('');
    const cooldownRef = useRef(null);
    const prevIdentifierRef = useRef('');

    const scriptData = getSetting('wsms-checkout-verify_data', {});
    const restUrl = scriptData.restUrl || '/wp-json/wsms/v1/';
    const nonce = scriptData.nonce || '';

    // Reset when identifier changes.
    useEffect(() => {
        if (identifier && identifier !== prevIdentifierRef.current) {
            prevIdentifierRef.current = identifier;
            setStatus(STATUS.idle);
            setSessionToken('');
            setOtp('');
            setErrorMsg('');
        }
    }, [identifier]);

    const apiCall = useCallback(async (endpoint, body, token) => {
        const headers = { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce };
        if (token) headers['X-Verification-Session'] = token;
        const res = await fetch(restUrl + endpoint, { method: 'POST', headers, body: JSON.stringify(body), credentials: 'same-origin' });
        return res.json();
    }, [restUrl, nonce]);

    const startCooldown = useCallback((seconds) => {
        setCooldown(seconds);
        clearInterval(cooldownRef.current);
        cooldownRef.current = setInterval(() => {
            setCooldown((prev) => {
                if (prev <= 1) { clearInterval(cooldownRef.current); return 0; }
                return prev - 1;
            });
        }, 1000);
    }, []);

    useEffect(() => () => clearInterval(cooldownRef.current), []);

    const handleSend = useCallback(async () => {
        setStatus(STATUS.sending);
        setErrorMsg('');
        try {
            const data = await apiCall('verify/send', { channel, identifier }, sessionToken || undefined);
            if (data.success) {
                setSessionToken(data.session_token);
                setStatus(STATUS.input);
                if (data.cooldown) startCooldown(data.cooldown);
            } else {
                setErrorMsg(data.message || 'Failed to send code.');
                setStatus(STATUS.error);
            }
        } catch {
            setErrorMsg('Network error. Please try again.');
            setStatus(STATUS.error);
        }
    }, [apiCall, channel, identifier, sessionToken, startCooldown]);

    const handleVerify = useCallback(async () => {
        if (otp.length < 4) return;
        setStatus(STATUS.verifying);
        setErrorMsg('');
        try {
            const data = await apiCall('verify/check', { channel, identifier, code: otp }, sessionToken);
            if (data.success) {
                setStatus(STATUS.verified);
                onVerified(data.session_token || sessionToken);
            } else {
                setErrorMsg(data.message || 'Invalid code.');
                setStatus(STATUS.input);
            }
        } catch {
            setErrorMsg('Network error. Please try again.');
            setStatus(STATUS.input);
        }
    }, [apiCall, channel, identifier, otp, sessionToken, onVerified]);

    const h = wp.element.createElement;
    const label = channel === 'phone' ? 'phone number' : 'email address';

    if (status === STATUS.verified) {
        return h(NoticeBanner, { type: 'success' }, `Your ${label} has been verified.`);
    }

    if (status === STATUS.error) {
        return h(NoticeBanner, { type: 'error' },
            errorMsg, ' ',
            h('a', {
                href: '#',
                className: 'wc-block-components-checkout-return-to-cart-button',
                role: 'button',
                onClick: (e) => { e.preventDefault(); handleSend(); },
            }, 'Try again'),
        );
    }

    if (status === STATUS.idle) {
        return h(NoticeBanner, { type: 'info' },
            `Please verify your ${label}. `,
            h('a', {
                href: '#',
                className: 'wc-block-components-checkout-return-to-cart-button',
                role: 'button',
                onClick: (e) => { e.preventDefault(); handleSend(); },
            }, 'Send verification code'),
        );
    }

    if (status === STATUS.sending) {
        return h(NoticeBanner, { type: 'info' }, 'Sending verification code...');
    }

    // Input + Verifying — show OTP input.
    return h('div', { className: 'wc-block-components-address-form' },
        errorMsg && h(NoticeBanner, { type: 'error' }, errorMsg),
        h('div', { className: 'wc-block-components-text-input is-active' },
            h('input', {
                type: 'text',
                inputMode: 'numeric',
                autoComplete: 'one-time-code',
                maxLength: 6,
                value: otp,
                onChange: (e) => setOtp(e.target.value.replace(/\D/g, '')),
                onKeyDown: (e) => { if (e.key === 'Enter') { e.preventDefault(); handleVerify(); } },
                disabled: status === STATUS.verifying,
                placeholder: ' ',
                id: 'wsms-otp-input',
            }),
            h('label', { htmlFor: 'wsms-otp-input' }, 'Verification code'),
        ),
        h('div', { className: 'wsms-checkout-verify-actions' },
            h('button', {
                type: 'button',
                className: 'wc-block-components-button wp-element-button contained',
                onClick: handleVerify,
                disabled: otp.length < 4 || status === STATUS.verifying,
            }, h('span', { className: 'wc-block-components-button__text' },
                status === STATUS.verifying ? 'Verifying...' : 'Verify',
            )),
            ' ',
            cooldown <= 0
                ? h('a', {
                    href: '#',
                    className: 'wc-block-components-checkout-return-to-cart-button',
                    role: 'button',
                    onClick: (e) => { e.preventDefault(); if (status !== STATUS.verifying) handleSend(); },
                }, 'Resend code')
                : h('span', { className: 'wc-block-components-checkout-step__description' },
                    `Resend in ${cooldown}s`,
                ),
        ),
    );
}
