import { useState, useEffect, useRef } from 'preact/hooks';
import { useAutoFocus } from '../../hooks/useAutoFocus';
import { useLocation } from 'preact-iso';
import { api } from '../../api/client';
import {
    authError,
    authLoading,
    authStep,
    identifyResult,
    enteredIdentifier,
    selectedMethod,
    resetIdentifyFlow,
} from '../../signals/auth';
import { handleAuthResponse, extractError } from '../../utils/auth';
import { authUrl } from '../../utils/urls';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { Input } from '../ui/Input';
import { Label } from '../ui/Label';
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
            authError.value = extractError(err);
            captcha.reset();
        } finally {
            authLoading.value = false;
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
            authError.value = extractError(err);
        } finally {
            authLoading.value = false;
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
            <div className="space-y-4 animate-fade-in">
                <p className="text-sm text-muted-foreground text-center">Choose a sign-in method</p>
                {methods.map((m) => (
                    <Button
                        key={m.method}
                        variant="outline"
                        className="w-full"
                        onClick={() => switchMethod(m.method)}
                    >
                        {getMethodLabel(m.method)}
                    </Button>
                ))}
                <Button variant="link" className="w-full" onClick={() => setShowMethodPicker(false)}>
                    Cancel
                </Button>
            </div>
        );
    }

    return (
        <div className="space-y-4 animate-fade-in">
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="mb-4" />
            <Alert variant="success" message={successMsg} className="mb-4" />

            <p className="text-sm text-muted-foreground text-center">
                Signing in as <strong>{maskedId}</strong>
            </p>

            {/* Password form */}
            {activeMethod === 'password' && (
                <form onSubmit={handlePasswordSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label for="wsms-password">Password</Label>
                        <Input
                            ref={passwordRef}
                            id="wsms-password"
                            type="password"
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
                    <Button className="w-full" type="submit" disabled={authLoading.value || (needsCaptcha && !captcha.token)}>
                        {authLoading.value ? 'Signing in...' : 'Continue'}
                    </Button>

                    {hasOtpAlt && altOtpMethod && (
                        <div className="text-center">
                            <Button variant="link" type="button" onClick={() => switchMethod(altOtpMethod.method)}>
                                {altOtpMethod.channel === 'email' ? 'Email me a code instead' : 'Text me a code instead'}
                            </Button>
                        </div>
                    )}
                </form>
            )}

            {/* OTP send flow */}
            {activeMethod && activeMethod !== 'password' && !activeMethod.includes('magic_link') && !codeSent && (
                <div className="space-y-4">
                    {needsCaptcha && (
                        <CaptchaWidget
                            provider={captcha.provider}
                            siteKey={captcha.siteKey}
                            onVerify={captcha.setToken}
                            resetRef={captcha.resetRef}
                        />
                    )}
                    <Button
                        className="w-full"
                        onClick={() => sendChallenge(activeMethod)}
                        disabled={authLoading.value || (needsCaptcha && !captcha.token)}
                    >
                        {authLoading.value ? 'Sending...' : 'Send Code'}
                    </Button>

                    {hasPasswordAlt && (
                        <div className="text-center">
                            <Button variant="link" type="button" onClick={() => switchMethod('password')}>
                                Use password instead
                            </Button>
                        </div>
                    )}
                </div>
            )}

            {/* Magic link only */}
            {activeMethod && activeMethod.includes('magic_link') && !successMsg && (
                <div className="space-y-4">
                    {needsCaptcha && (
                        <CaptchaWidget
                            provider={captcha.provider}
                            siteKey={captcha.siteKey}
                            onVerify={captcha.setToken}
                            resetRef={captcha.resetRef}
                        />
                    )}
                    <Button
                        className="w-full"
                        onClick={() => sendChallenge(activeMethod)}
                        disabled={authLoading.value || (needsCaptcha && !captcha.token)}
                    >
                        {authLoading.value ? 'Sending...' : 'Send Login Link'}
                    </Button>

                    {hasPasswordAlt && (
                        <div className="text-center">
                            <Button variant="link" type="button" onClick={() => switchMethod('password')}>
                                Use password instead
                            </Button>
                        </div>
                    )}
                </div>
            )}

            {/* All methods link */}
            {alternativeMethods.length > 1 && (
                <div className="text-center">
                    <Button variant="link" type="button" onClick={() => setShowMethodPicker(true)}>
                        Use a different method
                    </Button>
                </div>
            )}

            {/* Back to identifier */}
            <div className="text-center">
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
