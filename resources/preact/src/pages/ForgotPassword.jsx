import { useState } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
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
            setSuccess(res.message || __('If that email exists, a reset link has been sent.', 'wp-sms'));
        } catch (err) {
            authError.value = extractError(err).message;
            captcha.reset();
        } finally {
            authLoading.value = false;
        }
    }

    return (
        <AuthLayout
            title={__('Forgot Password', 'wp-sms')}
            subtitle={__('Enter your email and we\'ll send you a reset link.', 'wp-sms')}
            footer={<AuthLink href={authUrl('/login')}>{__('Back to login', 'wp-sms')}</AuthLink>}
        >
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="wsms-auth-mb-4" />
            <Alert variant="success" message={success} className="wsms-auth-mb-4" />

            {!success && (
                <form onSubmit={handleSubmit} className="wsms-auth-stack-4">
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-forgot-email">{__('Email', 'wp-sms')}</Label>
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
                    <Button className="wsms-auth-full" type="submit" loading={authLoading.value} disabled={needsCaptcha && !captcha.token}>
                        {authLoading.value ? __('Sending\u2026', 'wp-sms') : __('Send Reset Link', 'wp-sms')}
                    </Button>
                </form>
            )}
        </AuthLayout>
    );
}
