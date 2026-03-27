import { useState, useEffect, useRef } from 'preact/hooks';
import { useAutoFocus } from '../../hooks/useAutoFocus';
import { useLocation } from 'preact-iso';
import { api } from '../../api/client';
import {
    authError,
    authLoading,
    stopLoading,
    identifyResult,
    enteredIdentifier,
    selectedMethod,
    resetIdentifyFlow,
} from '../../signals/auth';
import { handleAuthResponse, extractError, handleRecoveryAction } from '../../utils/auth';
import { authUrl } from '../../utils/urls';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { Input } from '../ui/Input';
import { PasswordInput } from '../ui/PasswordInput';
import { Label } from '../ui/Label';
import { LockoutCountdown } from '../ui/LockoutCountdown';
import { CaptchaWidget } from '../CaptchaWidget';
import { useCaptcha } from '../../hooks/useCaptcha';


export function AuthenticateStep() {
    const { route } = useLocation();
    const result = identifyResult.value;
    const identifier = enteredIdentifier.value;
    const activeMethod = selectedMethod.value || result?.default_method;
    const [password, setPassword] = useState('');
    const [showMethodPicker, setShowMethodPicker] = useState(false);
    const [codeSent, setCodeSent] = useState(false);
    const [successMsg, setSuccessMsg] = useState('');
    const [lockoutSeconds, setLockoutSeconds] = useState(0);
    const captcha = useCaptcha();
    const needsCaptcha = captcha.isRequiredFor('login');

    const autoSentRef = useRef(false);
    const passwordRef = useAutoFocus(activeMethod === 'password');

    // Auto-send challenge when there's only one method and it's not password.
    useEffect(() => {
        if (!result || !result.user_found || autoSentRef.current) return;
        const methods = result.available_methods || [];
        if (methods.length === 1 && activeMethod && activeMethod !== 'password') {
            autoSentRef.current = true;
            sendChallenge(activeMethod);
        }
    }, [result, activeMethod]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!result || !result.user_found) return null;

    const maskedId = result.meta?.masked_identifier || identifier;
    const methods = result.available_methods || [];

    // Compute alternative methods for "use a different method" link.
    const alternativeMethods = methods.filter((m) => m.method !== activeMethod);
    const hasPasswordAlt = methods.some((m) => m.method === 'password') && activeMethod !== 'password';
    const hasOtpAlt = methods.some((m) => m.type === 'otp') && activeMethod === 'password';
    const altOtpMethod = methods.find((m) => m.type === 'otp' && m.method !== activeMethod);

    async function handlePasswordSubmit(e) {
        e.preventDefault();
        authError.value = null;
        authLoading.value = true;

        try {
            const res = await api.post('/auth/login', { username: identifier, password }, captcha.getHeaders());
            handleAuthResponse(res, route);
        } catch (err) {
            const details = extractError(err);
            if (!handleRecoveryAction(details, route)) {
                if (details.retryAfter) {
                    setLockoutSeconds(details.retryAfter);
                    authError.value = null;
                } else {
                    authError.value = details.message;
                }
                captcha.reset();
            }
        } finally {
            stopLoading();
        }
    }

    async function sendChallenge(method) {
        const m = methods.find((x) => x.method === method);
        if (!m) return;

        authError.value = null;
        authLoading.value = true;

        try {
            const res = await api.post('/auth/login/passwordless', {
                method: m.channel,
                identifier,
            }, captcha.getHeaders());

            const status = handleAuthResponse(res, route);

            if (status === 'challenge_sent') {
                // Check if this is magic-link-only.
                const hasMagicLink = methods.some((x) => x.method === `${m.channel}_magic_link`);
                const hasOtp = methods.some((x) => x.method === `${m.channel}_otp`);

                if (hasMagicLink && !hasOtp) {
                    const target = m.channel === 'email' ? 'email' : 'SMS';
                    setSuccessMsg(`Check your ${target} for a login link.`);
                } else {
                    setCodeSent(true);
                    route(authUrl('/verify'));
                }
            }
        } catch (err) {
            const details = extractError(err);
            if (!handleRecoveryAction(details, route)) {
                authError.value = details.message;
            }
        } finally {
            stopLoading();
        }
    }

    function switchMethod(method) {
        selectedMethod.value = method;
        setShowMethodPicker(false);
        setCodeSent(false);
        setSuccessMsg('');
        authError.value = null;
    }

    // Method picker overlay.
    if (showMethodPicker) {
        return (
            <div className="wsms-auth-stack-4 wsms-auth-fade-in">
                <p className="wsms-auth-text-sm wsms-auth-text-muted wsms-auth-center">Choose a sign-in method</p>
                {methods.map((m) => (
                    <Button
                        key={m.method}
                        variant="outline"
                        className="wsms-auth-full"
                        onClick={() => switchMethod(m.method)}
                    >
                        {getMethodLabel(m.method)}
                    </Button>
                ))}
                <Button variant="link" className="wsms-auth-full" onClick={() => setShowMethodPicker(false)}>
                    Cancel
                </Button>
            </div>
        );
    }

    if (lockoutSeconds > 0) {
        return (
            <div className="wsms-auth-stack-4 wsms-auth-fade-in">
                <LockoutCountdown
                    seconds={lockoutSeconds}
                    onExpire={() => setLockoutSeconds(0)}
                />
                <div className="wsms-auth-center">
                    <Button variant="link" type="button" onClick={resetIdentifyFlow}>
                        Not {maskedId}? Use a different account
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className="wsms-auth-stack-4 wsms-auth-fade-in">
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="wsms-auth-mb-4" />
            <Alert variant="success" message={successMsg} className="wsms-auth-mb-4" />

            <p className="wsms-auth-text-sm wsms-auth-text-muted wsms-auth-center">
                Signing in as <strong>{maskedId}</strong>
            </p>

            {/* Password form */}
            {activeMethod === 'password' && (
                <form onSubmit={handlePasswordSubmit} className="wsms-auth-stack-4">
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-password">Password</Label>
                        <PasswordInput
                            ref={passwordRef}
                            id="wsms-password"
                            value={password}
                            onInput={(e) => setPassword(e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="current-password"
                        />
                    </div>
                    {needsCaptcha && (
                        <CaptchaWidget
                            provider={captcha.provider}
                            siteKey={captcha.siteKey}
                            onVerify={captcha.setToken}
                            resetRef={captcha.resetRef}
                        />
                    )}
                    <Button className="wsms-auth-full" type="submit" loading={authLoading.value} disabled={needsCaptcha && !captcha.token}>
                        {authLoading.value ? 'Signing in...' : 'Continue'}
                    </Button>

                    {hasOtpAlt && altOtpMethod && (
                        <div className="wsms-auth-center">
                            <Button variant="link" type="button" onClick={() => switchMethod(altOtpMethod.method)}>
                                {altOtpMethod.channel === 'email' ? 'Email me a code instead' : 'Text me a code instead'}
                            </Button>
                        </div>
                    )}
                </form>
            )}

            {/* OTP send flow */}
            {activeMethod && activeMethod !== 'password' && !activeMethod.includes('magic_link') && !codeSent && (
                <div className="wsms-auth-stack-4">
                    {needsCaptcha && (
                        <CaptchaWidget
                            provider={captcha.provider}
                            siteKey={captcha.siteKey}
                            onVerify={captcha.setToken}
                            resetRef={captcha.resetRef}
                        />
                    )}
                    <Button
                        className="wsms-auth-full"
                        onClick={() => sendChallenge(activeMethod)}
                        loading={authLoading.value}
                        disabled={needsCaptcha && !captcha.token}
                    >
                        {authLoading.value ? 'Sending...' : 'Send Code'}
                    </Button>

                    {hasPasswordAlt && (
                        <div className="wsms-auth-center">
                            <Button variant="link" type="button" onClick={() => switchMethod('password')}>
                                Use password instead
                            </Button>
                        </div>
                    )}
                </div>
            )}

            {/* Magic link only */}
            {activeMethod && activeMethod.includes('magic_link') && !successMsg && (
                <div className="wsms-auth-stack-4">
                    {needsCaptcha && (
                        <CaptchaWidget
                            provider={captcha.provider}
                            siteKey={captcha.siteKey}
                            onVerify={captcha.setToken}
                            resetRef={captcha.resetRef}
                        />
                    )}
                    <Button
                        className="wsms-auth-full"
                        onClick={() => sendChallenge(activeMethod)}
                        loading={authLoading.value}
                        disabled={needsCaptcha && !captcha.token}
                    >
                        {authLoading.value ? 'Sending...' : 'Send Login Link'}
                    </Button>

                    {hasPasswordAlt && (
                        <div className="wsms-auth-center">
                            <Button variant="link" type="button" onClick={() => switchMethod('password')}>
                                Use password instead
                            </Button>
                        </div>
                    )}
                </div>
            )}

            {/* All methods link */}
            {alternativeMethods.length > 1 && (
                <div className="wsms-auth-center">
                    <Button variant="link" type="button" onClick={() => setShowMethodPicker(true)}>
                        Use a different method
                    </Button>
                </div>
            )}

            {/* Back to identifier */}
            <div className="wsms-auth-center">
                <Button variant="link" type="button" onClick={resetIdentifyFlow}>
                    Not {maskedId}? Use a different account
                </Button>
            </div>
        </div>
    );
}

function getMethodLabel(method) {
    const labels = {
        password: 'Password',
        phone_otp: 'Phone code (SMS)',
        phone_magic_link: 'Phone login link',
        email_otp: 'Email code',
        email_magic_link: 'Email login link',
    };
    return labels[method] || method;
}
