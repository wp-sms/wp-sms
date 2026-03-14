import { render, h } from 'preact';
import { useState, useCallback, useEffect, useRef } from 'preact/hooks';
import { OtpInputLight } from './components/OtpInputLight';
import { useResendCooldown } from './hooks/useResendCooldown';
import './styles/verify-widget.css';

const { restUrl, nonce } = window.wsmsVerifyConfig || {};

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
    });

    let data;
    try { data = await res.json(); } catch { data = { message: 'Server error. Please try again.' }; }
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
            const msg = err?.message || 'Failed to send verification code.';
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
                setErrorMsg(res.message || 'Verification failed.');
                setState('input');
            }
        } catch (err) {
            setErrorMsg(err?.message || 'Verification failed.');
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
            setErrorMsg(err?.message || 'Failed to resend code.');
        }
    }, [channel, identifier, sessionToken, cooldown, resetCooldown]);

    // Verified
    if (state === 'verified') {
        return (
            <div className="wsms-vw">
                <div className="wsms-vw-verified" role="status">
                    <CheckIcon />
                    <span>Verification complete</span>
                </div>
            </div>
        );
    }

    // Sending
    if (state === 'sending') {
        return (
            <div className="wsms-vw">
                <div className="wsms-vw-sending" role="status" aria-live="polite">
                    <Spinner />
                    <span>Sending verification code&hellip;</span>
                </div>
            </div>
        );
    }

    // Initial error (no session yet)
    if (state === 'error' && !sessionToken) {
        return (
            <div className="wsms-vw">
                <div className="wsms-vw-error-box" role="alert">
                    <p>{errorMsg}</p>
                    <button type="button" className="wsms-vw-retry" onClick={() => sendCode(null)}>
                        Send a new code
                    </button>
                </div>
            </div>
        );
    }

    // OTP input / verifying
    if (state === 'input' || state === 'verifying') {
        return (
            <div className="wsms-vw">
                <div className={state === 'verifying' ? 'wsms-vw-verifying' : ''}>
                    <p className="wsms-vw-label">
                        We sent a {codeLength}-digit code to <strong>{maskedId}</strong>
                    </p>

                    {errorMsg && (
                        <p className="wsms-vw-error-msg" role="alert">{errorMsg}</p>
                    )}

                    <OtpInputLight
                        length={codeLength}
                        onComplete={handleVerify}
                        disabled={state === 'verifying'}
                        autoFocus={true}
                    />

                    {state === 'verifying' && (
                        <div className="wsms-vw-verifying-indicator" role="status" aria-live="polite">
                            <Spinner />
                            <span>Verifying&hellip;</span>
                        </div>
                    )}

                    <div className="wsms-vw-actions">
                        <button
                            type="button"
                            className="wsms-vw-resend"
                            onClick={handleResend}
                            disabled={cooldown > 0 || state === 'verifying'}
                        >
                            {cooldown > 0 ? `Resend code in ${cooldown}s` : 'Resend code'}
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    // Prompt — waiting for user to request code
    if (state === 'idle' && identifier) {
        const channelLabel = channel === 'phone' ? 'phone number' : 'email address';
        return (
            <div className="wsms-vw">
                <p className="wsms-vw-label">
                    We need to verify your {channelLabel}
                </p>
                <button type="button" className="wsms-vw-send-btn" onClick={() => sendCode(null)}>
                    Send verification code
                </button>
            </div>
        );
    }

    return null;
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
