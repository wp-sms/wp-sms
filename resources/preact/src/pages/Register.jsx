import { useState, useEffect } from 'preact/hooks';
import { api } from '../api/client';
import { registrationFields, socialProviders } from '../signals/config';
import { authError, authLoading, registrationToken, pendingVerifications } from '../signals/auth';
import { extractError, friendlySocialError } from '../utils/auth';
import { authUrl, getQueryParam } from '../utils/urls';
import { AuthLayout } from '../layouts/AuthLayout';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { Label } from '../components/ui/Label';
import { AuthLink } from '../components/AuthLink';
import { PhoneInput } from '../components/PhoneInput';
import { RegisterVerifyStep } from '../components/steps/RegisterVerifyStep';
import { CaptchaWidget } from '../components/CaptchaWidget';
import { useCaptcha } from '../hooks/useCaptcha';
import { SocialLoginButtons } from '../components/SocialLoginButtons';
import { SocialDivider } from '../components/SocialDivider';

export function Register() {
    const fields = registrationFields.value;
    const captcha = useCaptcha();
    const needsCaptcha = captcha.isRequiredFor('register');
    const [form, setForm] = useState({
        email: '',
        password: '',
        username: '',
        display_name: '',
        first_name: '',
        last_name: '',
        phone: '',
    });
    const [success, setSuccess] = useState('');
    const [verifying, setVerifying] = useState(false);

    useEffect(() => {
        const socialError = getQueryParam('social_error');
        if (socialError) {
            authError.value = friendlySocialError(socialError);
            window.history.replaceState({}, '', window.location.pathname);
        }
    }, []);

    function updateField(name, value) {
        setForm((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        authError.value = null;
        authLoading.value = true;

        const body = {};
        for (const f of fields) {
            if (form[f]) body[f] = form[f];
        }

        try {
            const res = await api.post('/auth/register', body, captcha.getHeaders());
            if (res.success) {
                if (res.pending_verifications?.length > 0) {
                    registrationToken.value = res.registration_token;
                    pendingVerifications.value = res.pending_verifications;
                    setVerifying(true);
                } else {
                    setSuccess(res.message || 'Account created successfully.');
                }
            }
        } catch (err) {
            authError.value = extractError(err);
            captcha.reset();
        } finally {
            authLoading.value = false;
        }
    }

    if (verifying) {
        return (
            <AuthLayout
                title="Verify Your Account"
                footer={<AuthLink href={authUrl('/login')}>Back to login</AuthLink>}
            >
                <RegisterVerifyStep onComplete={() => { window.location.href = authUrl('/login'); }} />
            </AuthLayout>
        );
    }

    if (success) {
        return (
            <AuthLayout
                title="Account Created"
                footer={<AuthLink href={authUrl('/login')}>Back to login</AuthLink>}
            >
                <Alert variant="success" message={success} />
            </AuthLayout>
        );
    }

    return (
        <AuthLayout
            title="Create Account"
            footer={<AuthLink href={authUrl('/login')}>Already have an account? Sign in</AuthLink>}
        >
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="mb-4" />

            {socialProviders.value.length > 0 && <>
                <SocialLoginButtons intent="register" />
                <SocialDivider />
            </>}

            <form onSubmit={handleSubmit} className="space-y-4">
                {fields.includes('username') && (
                    <div className="space-y-2">
                        <Label for="wsms-reg-username">Username</Label>
                        <Input
                            id="wsms-reg-username"
                            type="text"
                            value={form.username}
                            onInput={(e) => updateField('username', e.target.value)}
                            disabled={authLoading.value}
                            autoComplete="username"
                        />
                    </div>
                )}

                {fields.includes('display_name') && (
                    <div className="space-y-2">
                        <Label for="wsms-reg-name">Display Name</Label>
                        <Input
                            id="wsms-reg-name"
                            type="text"
                            value={form.display_name}
                            onInput={(e) => updateField('display_name', e.target.value)}
                            disabled={authLoading.value}
                            autoComplete="name"
                        />
                    </div>
                )}

                {fields.includes('first_name') && (
                    <div className="space-y-2">
                        <Label for="wsms-reg-first-name">First Name</Label>
                        <Input
                            id="wsms-reg-first-name"
                            type="text"
                            value={form.first_name}
                            onInput={(e) => updateField('first_name', e.target.value)}
                            disabled={authLoading.value}
                            autoComplete="given-name"
                        />
                    </div>
                )}

                {fields.includes('last_name') && (
                    <div className="space-y-2">
                        <Label for="wsms-reg-last-name">Last Name</Label>
                        <Input
                            id="wsms-reg-last-name"
                            type="text"
                            value={form.last_name}
                            onInput={(e) => updateField('last_name', e.target.value)}
                            disabled={authLoading.value}
                            autoComplete="family-name"
                        />
                    </div>
                )}

                {fields.includes('email') && (
                    <div className="space-y-2">
                        <Label for="wsms-reg-email">Email</Label>
                        <Input
                            id="wsms-reg-email"
                            type="email"
                            value={form.email}
                            onInput={(e) => updateField('email', e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="email"
                        />
                    </div>
                )}

                {fields.includes('phone') && (
                    <div className="space-y-2">
                        <Label>Phone Number</Label>
                        <PhoneInput
                            value={form.phone}
                            onChange={(val) => updateField('phone', val)}
                            disabled={authLoading.value}
                        />
                    </div>
                )}

                {fields.includes('password') && (
                    <div className="space-y-2">
                        <Label for="wsms-reg-password">Password</Label>
                        <Input
                            id="wsms-reg-password"
                            type="password"
                            value={form.password}
                            onInput={(e) => updateField('password', e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="new-password"
                        />
                    </div>
                )}

                {needsCaptcha && (
                    <CaptchaWidget
                        provider={captcha.provider}
                        siteKey={captcha.siteKey}
                        onVerify={captcha.setToken}
                        resetRef={captcha.resetRef}
                    />
                )}
                <Button className="w-full" type="submit" disabled={authLoading.value || (needsCaptcha && !captcha.token)}>
                    {authLoading.value ? 'Creating account\u2026' : 'Create Account'}
                </Button>
            </form>
        </AuthLayout>
    );
}
