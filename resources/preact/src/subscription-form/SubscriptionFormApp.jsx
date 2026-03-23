import { useState, useCallback } from 'preact/hooks';
import { OtpInput } from '../components/OtpInput';
import { PhoneInput } from '../components/PhoneInput';
import { useResendCooldown } from '../hooks/useResendCooldown';

const STATE = { FORM: 'form', SUBMITTING: 'submitting', VERIFY: 'verify', SUCCESS: 'success' };
const INPUT_TYPES = { email: 'email' };

function PoweredBy() {
    return (
        <a href="https://wsms.io" target="_blank" rel="noopener noreferrer" class="wsms-sub-form__branding">
            <svg viewBox="0 0 512 512" fill="currentColor" width="12" height="12">
                <path d="M116 167.808V257.015L312.101 153.007V64L116 167.808Z" />
                <path d="M116 285.7V374.707L395.989 226.296V137.289L116 285.7Z" />
                <path d="M396 254.984V342.991L200.116 447.999L199.898 357.992L396 254.984Z" />
            </svg>
            <span>Powered by <strong>WSMS</strong></span>
        </a>
    );
}

export function SubscriptionFormApp({ config }) {
    const [state, setState] = useState(STATE.FORM);
    const [error, setError] = useState(null);
    const [values, setValues] = useState({});
    const [sessionToken, setSessionToken] = useState(null);
    const [maskedIdentifier, setMaskedIdentifier] = useState('');
    const [verifying, setVerifying] = useState(false);
    const [cooldown, resetCooldown] = useResendCooldown(0);

    const { fields, buttonText, successMessage, redirectUrl, restUrl, nonce } = config;
    const slug = config.slug;

    const apiCall = useCallback(async (endpoint, body) => {
        const res = await fetch(`${restUrl}subscribe/${slug}${endpoint}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify(body),
        });
        return res.json();
    }, [restUrl, slug, nonce]);

    const handleSubmit = useCallback(async (e) => {
        e.preventDefault();
        setError(null);
        setState(STATE.SUBMITTING);

        const data = { ...values };
        const hpField = e.target.querySelector('[name="_hp"]');
        if (hpField) {
            data._hp = hpField.value;
        }

        try {
            const result = await apiCall('', data);

            if (!result.success) {
                setError(result.message || 'Submission failed.');
                setState(STATE.FORM);
                return;
            }

            if (result.session_token) {
                setSessionToken(result.session_token);
                setMaskedIdentifier(result.masked_identifier || '');
                resetCooldown(60);
                setState(STATE.VERIFY);
            } else {
                if (redirectUrl) {
                    window.location.href = redirectUrl;
                    return;
                }
                setState(STATE.SUCCESS);
            }
        } catch {
            setError('An error occurred. Please try again.');
            setState(STATE.FORM);
        }
    }, [values, apiCall, redirectUrl, resetCooldown]);

    const handleVerify = useCallback(async (code) => {
        setError(null);
        setVerifying(true);

        try {
            const result = await apiCall('/verify', {
                code,
                session_token: sessionToken,
            });

            if (!result.success) {
                setError(result.message || 'Verification failed.');
                setVerifying(false);
                return;
            }

            if (redirectUrl) {
                window.location.href = redirectUrl;
                return;
            }
            setState(STATE.SUCCESS);
        } catch {
            setError('An error occurred. Please try again.');
            setVerifying(false);
        }
    }, [sessionToken, apiCall, redirectUrl]);

    const handleResend = useCallback(async () => {
        if (cooldown > 0) return;
        setError(null);
        try {
            const result = await apiCall('', values);
            if (!result.success) {
                setError(result.message || 'Failed to resend code.');
                return;
            }
            if (result.session_token) {
                setSessionToken(result.session_token);
                setMaskedIdentifier(result.masked_identifier || '');
                resetCooldown(60);
            }
        } catch {
            setError('An error occurred. Please try again.');
        }
    }, [values, apiCall, cooldown, resetCooldown]);

    const updateField = useCallback((key, value) => {
        setValues((prev) => ({ ...prev, [key]: value }));
    }, []);

    const isSubmitting = state === STATE.SUBMITTING;

    let content;

    if (state === STATE.SUCCESS) {
        content = (
            <div class="wsms-sub-form__success">
                <div class="wsms-sub-form__success-icon">✓</div>
                <div class="wsms-sub-form__success-msg">{successMessage}</div>
            </div>
        );
    } else if (state === STATE.VERIFY) {
        content = (
            <div class="wsms-sub-form__verify">
                <div class="wsms-sub-form__verify-msg">
                    We sent a verification code to{' '}
                    <span class="wsms-sub-form__verify-masked">{maskedIdentifier}</span>
                </div>

                <OtpInput
                    variant="subscription"
                    onComplete={handleVerify}
                    disabled={verifying}
                    autoFocus
                />

                <button
                    type="button"
                    class="wsms-sub-form__resend"
                    disabled={cooldown > 0}
                    onClick={handleResend}
                >
                    {cooldown > 0 ? `Resend in ${cooldown}s` : 'Resend code'}
                </button>

                {error && <div class="wsms-sub-form__error">{error}</div>}
            </div>
        );
    } else {
        content = (
            <form onSubmit={handleSubmit}>
                {fields.map((field) => (
                    <div class="wsms-sub-form__field" key={field.key}>
                        <label class={`wsms-sub-form__label${field.required ? ' wsms-sub-form__label--required' : ''}`}>
                            {field.label || field.key}
                        </label>
                        {field.key === 'phone' ? (
                            <PhoneInput
                                value={values[field.key] || ''}
                                onChange={(e164) => updateField(field.key, e164)}
                                disabled={isSubmitting}
                                config={config.phoneInput}
                            />
                        ) : (
                            <input
                                type={INPUT_TYPES[field.key] || 'text'}
                                class="wsms-sub-form__input"
                                name={field.key}
                                required={field.required}
                                value={values[field.key] || ''}
                                onInput={(e) => updateField(field.key, e.target.value)}
                                disabled={isSubmitting}
                            />
                        )}
                    </div>
                ))}

                <div class="wsms-sub-form__hp" aria-hidden="true">
                    <input type="text" name="_hp" tabIndex={-1} autoComplete="off" />
                </div>

                <button type="submit" class="wsms-sub-form__btn" disabled={isSubmitting}>
                    {isSubmitting && <span class="wsms-sub-form__spinner" />}
                    {buttonText || 'Subscribe'}
                </button>

                {error && <div class="wsms-sub-form__error">{error}</div>}
            </form>
        );
    }

    return (
        <div class="wsms-sub-form">
            {content}
            <PoweredBy />
        </div>
    );
}
