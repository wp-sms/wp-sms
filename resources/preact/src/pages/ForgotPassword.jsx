import { useState } from 'preact/hooks';
import { useAutoFocus } from '../hooks/useAutoFocus';
import { api } from '../api/client';
import { authError, authLoading } from '../signals/auth';
import { extractError } from '../utils/auth';
import { authUrl } from '../utils/urls';
import { AuthLayout } from '../layouts/AuthLayout';
import { alreadySignedIn } from '../components/AlreadySignedIn';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { Label } from '../components/ui/Label';
import { AuthLink } from '../components/AuthLink';
import { CaptchaWidget } from '../components/CaptchaWidget';
import { useCaptcha } from '../hooks/useCaptcha';

export function ForgotPassword() {
    const [email, setEmail] = useState('');
    const [success, setSuccess] = useState('');
    const emailRef = useAutoFocus();
    const captcha = useCaptcha();
    const needsCaptcha = captcha.isRequiredFor('forgot_password');

    const guard = alreadySignedIn();
    if (guard) return guard;

    async function handleSubmit(e) {
        e.preventDefault();
        authError.value = null;
        authLoading.value = true;

        try {
            const res = await api.post('/auth/forgot-password', { email }, captcha.getHeaders());
            setSuccess(res.message || 'If that email exists, a reset link has been sent.');
        } catch (err) {
            authError.value = extractError(err).message;
            captcha.reset();
        } finally {
            authLoading.value = false;
        }
    }

    return (
        <AuthLayout
            title="Forgot Password"
            subtitle="Enter your email and we'll send you a reset link."
            footer={<AuthLink href={authUrl('/login')}>Back to login</AuthLink>}
        >
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="wsms-auth-mb-4" />
            <Alert variant="success" message={success} className="wsms-auth-mb-4" />

            {!success && (
                <form onSubmit={handleSubmit} className="wsms-auth-stack-4">
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-forgot-email">Email</Label>
                        <Input
                            ref={emailRef}
                            id="wsms-forgot-email"
                            type="email"
                            value={email}
                            onInput={(e) => setEmail(e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="email"
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
                    <Button className="wsms-auth-full" type="submit" disabled={authLoading.value || (needsCaptcha && !captcha.token)}>
                        {authLoading.value ? 'Sending\u2026' : 'Send Reset Link'}
                    </Button>
                </form>
            )}
        </AuthLayout>
    );
}
