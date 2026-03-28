import { render, h } from 'preact';
import { useState, useCallback, useEffect, useRef } from 'preact/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { OtpInput } from './components/OtpInput';
import { useResendCooldown } from './hooks/useResendCooldown';
import './styles/verify-widget.css';

const { restUrl, nonce, primaryColor } = window.wsmsVerifyConfig || {};

async function apiPost(endpoint, body, sessionToken) {
    const headers = {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
    };
    if (sessionToken) {
        headers['X-Verification-Session'] = sessionToken;
    }

    const res = await fetch(`${restUrl}${endpoint}`, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify(body),
        signal: AbortSignal.timeout(15000),
    });

    let data;
    try { data = await res.json(); } catch { data = { message: __('Server error. Please try again.', 'wp-sms') }; }
    if (!res.ok) throw data;
    return data;
}

function CheckIcon() {
    return (
        <svg className="wsms-vw-check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="20 6 9 17 4 12" />
        </svg>
    );
}

function Spinner() {
    return <span className="wsms-vw-spinner" aria-hidden="true" />;
}

function PoweredBy() {
    return (
        <div className="wsms-vw-powered-by">
            {sprintf(__('powered by %s', 'wp-sms'), 'WSMS')}
        </div>
    );
}

function VerifyWidget({ channel, identifier, onVerified, onError, codeLength = 6 }) {
    const [state, setState] = useState('idle');
    const [sessionToken, setSessionToken] = useState(null);
    const [maskedId, setMaskedId] = useState('');
    const [errorMsg, setErrorMsg] = useState('');
    const [cooldown, resetCooldown] = useResendCooldown(0);
    const prevIdentifier = useRef(identifier);
    const sendingRef = useRef(false);

    // Reset to prompt when identifier changes (user edited the field).
    useEffect(() => {
        if (prevIdentifier.current !== identifier) {
            prevIdentifier.current = identifier;
            setState('idle');
            setSessionToken(null);
            setMaskedId('');
            setErrorMsg('');
        }
    }, [identifier]);

    const sendCode = useCallback(async (token) => {
        if (sendingRef.current) return;
        sendingRef.current = true;
        setState('sending');
        setErrorMsg('');
        try {
            const res = await apiPost('verify/send', { channel, identifier }, token);
            setSessionToken(res.session_token);
            setMaskedId(res.masked_identifier || '');
            resetCooldown(60);
            setState('input');
        } catch (err) {
            const msg = err?.message || __('Failed to send verification code.', 'wp-sms');
            setErrorMsg(msg);
            setState('error');
            onError?.(msg);
        } finally {
            sendingRef.current = false;
        }
    }, [channel, identifier, onError, resetCooldown]);

    const handleVerify = useCallback(async (code) => {
        setState('verifying');
        try {
            const res = await apiPost('verify/check', { channel, identifier, code }, sessionToken);
            if (res.success) {
                setState('verified');
                onVerified?.(res.session_token || sessionToken);
            } else {
                setErrorMsg(res.message || __('Verification failed.', 'wp-sms'));
                setState('input');
            }
        } catch (err) {
            setErrorMsg(err?.message || __('Verification failed.', 'wp-sms'));
            setState('input');
            onError?.(err?.message);
        }
    }, [channel, identifier, sessionToken, onVerified, onError]);

    const handleResend = useCallback(async () => {
        if (cooldown > 0) return;
        try {
            const res = await apiPost('verify/send', { channel, identifier }, sessionToken);
            setSessionToken(res.session_token);
            resetCooldown(60);
            setErrorMsg('');
        } catch (err) {
            setErrorMsg(err?.message || __('Failed to resend code.', 'wp-sms'));
        }
    }, [channel, identifier, sessionToken, cooldown, resetCooldown]);

    // No identifier — render nothing
    if (state === 'idle' && !identifier) {
        return null;
    }

    let content;

    if (state === 'verified') {
        content = (
            <div className="wsms-vw-verified" role="status">
                <CheckIcon />
                <span>{__('Verification complete', 'wp-sms')}</span>
            </div>
        );
    } else if (state === 'sending') {
        content = (
            <div className="wsms-vw-sending" role="status" aria-live="polite">
                <Spinner />
                <span>{__('Sending verification code\u2026', 'wp-sms')}</span>
            </div>
        );
    } else if (state === 'error' && !sessionToken) {
        content = (
            <div className="wsms-vw-error-box" role="alert">
                <p>{errorMsg}</p>
                <button type="button" className="wsms-vw-retry" onClick={() => sendCode(null)}>
                    {__('Send a new code', 'wp-sms')}
                </button>
            </div>
        );
    } else if (state === 'input' || state === 'verifying') {
        content = (
            <div className={state === 'verifying' ? 'wsms-vw-verifying' : ''}>
                <p className="wsms-vw-label">
                    {sprintf(__('We sent a %1$d-digit code to %2$s', 'wp-sms'), codeLength, maskedId)}
                </p>

                {errorMsg && (
                    <p className="wsms-vw-error-msg" role="alert">{errorMsg}</p>
                )}

                <OtpInput
                    variant="widget"
                    length={codeLength}
                    onComplete={handleVerify}
                    disabled={state === 'verifying'}
                    autoFocus={true}
                />

                {state === 'verifying' && (
                    <div className="wsms-vw-verifying-indicator" role="status" aria-live="polite">
                        <Spinner />
                        <span>{__('Verifying\u2026', 'wp-sms')}</span>
                    </div>
                )}

                <div className="wsms-vw-actions">
                    <button
                        type="button"
                        className="wsms-vw-resend"
                        onClick={handleResend}
                        disabled={cooldown > 0 || state === 'verifying'}
                    >
                        {cooldown > 0 ? sprintf(__('Resend code in %ds', 'wp-sms'), cooldown) : __('Resend code', 'wp-sms')}
                    </button>
                </div>
            </div>
        );
    } else {
        // idle with identifier — prompt to send code
        const channelLabel = channel === 'phone' ? __('phone number', 'wp-sms') : __('email address', 'wp-sms');
        content = (
            <>
                <p className="wsms-vw-label">
                    {sprintf(__('We need to verify your %s', 'wp-sms'), channelLabel)}
                </p>
                <button type="button" className="wsms-vw-send-btn" onClick={() => sendCode(null)}>
                    {__('Send verification code', 'wp-sms')}
                </button>
            </>
        );
    }

    const style = primaryColor ? { '--wsms-vw-link': primaryColor } : undefined;

    return (
        <div className="wsms-vw" style={style}>
            {content}
            <PoweredBy />
        </div>
    );
}

window.wsmsVerify = {
    mount(containerEl, options) {
        if (!containerEl || !options) return;

        render(
            h(VerifyWidget, {
                channel: options.channel,
                identifier: options.identifier,
                onVerified: options.onVerified,
                onError: options.onError,
                codeLength: options.codeLength || 6,
            }),
            containerEl,
        );
    },

    destroy(containerEl) {
        if (!containerEl) return;
        render(null, containerEl);
    },
};
