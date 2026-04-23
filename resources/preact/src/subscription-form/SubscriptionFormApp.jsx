import { useState, useCallback, useRef } from 'preact/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { CaptchaWidget } from '../components/CaptchaWidget';
import { OtpInput } from '../components/OtpInput';
import { PhoneInput } from '../components/PhoneInput';
import { useFormCaptcha } from '../hooks/useFormCaptcha';
import { useResendCooldown } from '../hooks/useResendCooldown';
import { api } from '../../../shared/rest-client';

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
            <span>{sprintf(__('Powered by %s', 'wp-sms'), 'WSMS')}</span>
        </a>
    );
}

export function SubscriptionFormApp({ config }) {
    const [state, setState] = useState(STATE.FORM);
    const [error, setError] = useState(null);
    const [values, setValues] = useState({});
    const [consentChecked, setConsentChecked] = useState(false);
    const [sessionToken, setSessionToken] = useState(null);
    const [maskedIdentifier, setMaskedIdentifier] = useState('');
    const [verifying, setVerifying] = useState(false);
    const [cooldown, resetCooldown] = useResendCooldown(0);

    const { fields, buttonText, successMessage, redirectUrl, captcha, consent } = config;
    const slug = config.slug;
    const submittingRef = useRef(false);
    const cap = useFormCaptcha(captcha, 'subscribe');

    const apiCall = useCallback(async (endpoint, body, extraHeaders = {}) => {
        try {
            return await api.post(`subscribe/${slug}${endpoint}`, body, { headers: extraHeaders });
        } catch (err) {
            return err && typeof err === 'object' ? err : { success: false };
        }
    }, [slug]);

    const handleSubmit = useCallback(async (e) => {
        e.preventDefault();
        if (submittingRef.current) return;
        submittingRef.current = true;
        setError(null);
        setState(STATE.SUBMITTING);

        const data = { ...values };
        const hpField = e.target.querySelector('[name="_hp"]');
        if (hpField) {
            data._hp = hpField.value;
        }
        if (consent && consentChecked) {
            data.consent = true;
        }

        try {
            const result = await apiCall('', data, cap.getHeaders());

            if (!result.success) {
                if (result.error === 'captcha_failed') {
                    cap.reset();
                }
                setError(result.message || __('Submission failed.', 'wp-sms'));
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
            setError(__('An error occurred. Please try again.', 'wp-sms'));
            setState(STATE.FORM);
        } finally {
            submittingRef.current = false;
        }
    }, [values, apiCall, redirectUrl, resetCooldown, cap, consent, consentChecked]);

    const handleVerify = useCallback(async (code) => {
        setError(null);
        setVerifying(true);

        try {
            const result = await apiCall('/verify', {
                code,
                session_token: sessionToken,
            });

            if (!result.success) {
                setError(result.message || __('Verification failed.', 'wp-sms'));
                setVerifying(false);
                return;
            }

            if (redirectUrl) {
                window.location.href = redirectUrl;
                return;
            }
            setState(STATE.SUCCESS);
        } catch {
            setError(__('An error occurred. Please try again.', 'wp-sms'));
            setVerifying(false);
        }
    }, [sessionToken, apiCall, redirectUrl]);

    const handleResend = useCallback(async () => {
        if (cooldown > 0) return;
        setError(null);
        try {
            const result = await apiCall('', values);
            if (!result.success) {
                setError(result.message || __('Failed to resend code.', 'wp-sms'));
                return;
            }
            if (result.session_token) {
                setSessionToken(result.session_token);
                setMaskedIdentifier(result.masked_identifier || '');
                resetCooldown(60);
            }
        } catch {
            setError(__('An error occurred. Please try again.', 'wp-sms'));
        }
    }, [values, apiCall, cooldown, resetCooldown]);

    const updateField = useCallback((key, value) => {
        setValues((prev) => ({ ...prev, [key]: value }));
    }, []);

    const isSubmitting = state === STATE.SUBMITTING;

    let content;

    if (state === STATE.SUCCESS) {
        content = (
            <div class="wsms-sub-form__success" role="status" aria-live="polite">
                <div class="wsms-sub-form__success-icon" aria-hidden="true">✓</div>
                <div class="wsms-sub-form__success-msg">{successMessage}</div>
            </div>
        );
    } else if (state === STATE.VERIFY) {
        content = (
            <div class="wsms-sub-form__verify">
                <div class="wsms-sub-form__verify-msg">
                    {sprintf(__('We sent a verification code to %s', 'wp-sms'), maskedIdentifier)}
                </div>

                <OtpInput
                    variant="subscription"
                    onComplete={handleVerify}
                    disabled={verifying}
                    autoFocus
                    error={!!error}
                />

                <button
                    type="button"
                    class="wsms-sub-form__resend"
                    disabled={cooldown > 0}
                    onClick={handleResend}
                >
                    {cooldown > 0 ? sprintf(__('Resend in %ds', 'wp-sms'), cooldown) : __('Resend code', 'wp-sms')}
                </button>

                {error && <div class="wsms-sub-form__error" role="alert">{error}</div>}
            </div>
        );
    } else {
        content = (
            <form onSubmit={handleSubmit}>
                {fields.map((field) => (
                    <div class="wsms-sub-form__field" key={field.key}>
                        <label htmlFor={`wsms-sub-${field.key}`} class={`wsms-sub-form__label${field.required ? ' wsms-sub-form__label--required' : ''}`}>
                            {field.label || field.key}
                        </label>
                        {field.key === 'phone' ? (
                            <PhoneInput
                                id={`wsms-sub-${field.key}`}
                                value={values[field.key] || ''}
                                onChange={(e164) => updateField(field.key, e164)}
                                disabled={isSubmitting}
                                config={config.phoneInput}
                                aria-label={field.label || field.key}
                            />
                        ) : (
                            <input
                                id={`wsms-sub-${field.key}`}
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

                <div class="wsms-sub-form__hp" aria-hidden="true" style="position:absolute;left:-9999px;height:0;overflow:hidden">
                    <input type="text" name="_hp" tabIndex={-1} autoComplete="off" />
                </div>

                {cap.enabled && (
                    <CaptchaWidget
                        provider={cap.provider}
                        siteKey={cap.siteKey}
                        onVerify={cap.setToken}
                        resetRef={cap.resetRef}
                    />
                )}

                {consent && (
                    // eslint-disable-next-line jsx-a11y/label-has-associated-control -- label wraps checkbox; dangerouslySetInnerHTML hides text from static analysis
                    <label class="wsms-sub-form__consent">
                        <input
                            type="checkbox"
                            checked={consentChecked}
                            onChange={(e) => setConsentChecked(e.target.checked)}
                            disabled={isSubmitting}
                        />
                        <span dangerouslySetInnerHTML={{ __html: consent.text }} />
                    </label>
                )}

                <button
                    type="submit"
                    class="wsms-sub-form__btn"
                    disabled={isSubmitting || (cap.enabled && !cap.token) || (consent?.required && !consentChecked)}
                >
                    {isSubmitting && <span class="wsms-sub-form__spinner" />}
                    {buttonText || __('Subscribe', 'wp-sms')}
                </button>

                {error && <div class="wsms-sub-form__error" role="alert">{error}</div>}
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
