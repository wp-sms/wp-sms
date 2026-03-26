import { useState, useEffect } from 'preact/hooks';
import { useAutoFocus } from '../hooks/useAutoFocus';
import { api } from '../api/client';
import { registrationFields, registrationFieldDefs, socialProviders, legalLinks, formSlug, formRedirectUrl, formName } from '../signals/config';
import { authError, authLoading, stopLoading, registrationToken, pendingVerifications } from '../signals/auth';
import { extractError, friendlySocialError, redirectTo } from '../utils/auth';
import { authUrl, getQueryParam } from '../utils/urls';
import { AuthLayout } from '../layouts/AuthLayout';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { Label } from '../components/ui/Label';
import { AuthLink } from '../components/AuthLink';
import { PhoneInput } from '../components/PhoneInput';
import { DynamicField } from '../components/DynamicField';
import { RegisterVerifyStep } from '../components/steps/RegisterVerifyStep';
import { CaptchaWidget } from '../components/CaptchaWidget';
import { useCaptcha } from '../hooks/useCaptcha';
import { SocialLoginButtons } from '../components/SocialLoginButtons';
import { SocialDivider } from '../components/SocialDivider';
import { brandingConfig } from '../signals/branding';
import { alreadySignedIn } from '../components/AlreadySignedIn';
import { SYSTEM_FIELD_IDS } from '../utils/fields';

export function Register() {
    const fields = registrationFields.value;
    const fieldDefs = registrationFieldDefs.value;
    const firstFieldRef = useAutoFocus();
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

    // Initialize custom field values when defs load, using default_value when available.
    useEffect(() => {
        if (fieldDefs.length > 0) {
            const customDefaults = {};
            for (const def of fieldDefs) {
                if (!SYSTEM_FIELD_IDS.includes(def.id) && !(def.id in form)) {
                    if (def.default_value !== undefined && def.default_value !== '') {
                        customDefaults[def.id] = def.default_value;
                    } else {
                        customDefaults[def.id] = def.type === 'checkbox' ? false : '';
                    }
                }
            }
            if (Object.keys(customDefaults).length > 0) {
                setForm((prev) => ({ ...prev, ...customDefaults }));
            }
        }
    }, [fieldDefs]); // eslint-disable-line react-hooks/exhaustive-deps -- reads form as guard, adding it would loop

    useEffect(() => {
        const socialError = getQueryParam('social_error');
        if (socialError) {
            authError.value = friendlySocialError(socialError);
            window.history.replaceState({}, '', window.location.pathname);
        }
    }, []);

    const guard = alreadySignedIn();
    if (guard) return guard;

    function updateField(name, value) {
        setForm((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        authError.value = null;
        authLoading.value = true;

        const body = {};
        // Include system fields from registration_fields.
        for (const f of fields) {
            if (form[f] !== undefined && form[f] !== '') body[f] = form[f];
        }
        // Include custom fields.
        for (const def of fieldDefs) {
            if (!SYSTEM_FIELD_IDS.includes(def.id) && form[def.id] !== undefined) {
                body[def.id] = form[def.id];
            }
        }

        if (formSlug.value) {
            body.form_id = formSlug.value;
        }

        try {
            const res = await api.post('/auth/register', body, captcha.getHeaders());
            if (res.success) {
                if (res.pending_verifications?.length > 0) {
                    registrationToken.value = res.session_token;
                    pendingVerifications.value = res.pending_verifications;
                    setVerifying(true);
                } else if (formRedirectUrl.value) {
                    redirectTo(formRedirectUrl.value);
                    return;
                } else {
                    setSuccess(res.message || 'Account created successfully.');
                }
            }
        } catch (err) {
            authError.value = extractError(err).message;
            captcha.reset();
        } finally {
            stopLoading();
        }
    }

    if (verifying) {
        const onVerifyComplete = () => {
            redirectTo(formRedirectUrl.value || authUrl('/login'));
        };

        return (
            <AuthLayout
                title="Verify Your Account"
                footer={<AuthLink href={authUrl('/login')}>Back to login</AuthLink>}
            >
                <RegisterVerifyStep onComplete={onVerifyComplete} />
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

    const hasSocial = socialProviders.value.length > 0;
    const socialPos = brandingConfig.value?.social_position ?? 'top';
    const socialBlock = hasSocial && (
        <>
            <SocialLoginButtons intent="register" />
            <SocialDivider />
        </>
    );

    const title = formName.value || 'Create Account';

    // Render a system field by ID
    function renderSystemField(id, isFirst) {
        switch (id) {
            case 'username':
                return (
                    <div className="space-y-2" key="username">
                        <Label for="wsms-reg-username">Username</Label>
                        <Input
                            ref={isFirst ? firstFieldRef : undefined}
                            id="wsms-reg-username"
                            type="text"
                            value={form.username}
                            onInput={(e) => updateField('username', e.target.value)}
                            disabled={authLoading.value}
                            autoComplete="username"
                        />
                    </div>
                );
            case 'display_name':
                return (
                    <div className="space-y-2" key="display_name">
                        <Label for="wsms-reg-name">Display Name</Label>
                        <Input
                            ref={isFirst ? firstFieldRef : undefined}
                            id="wsms-reg-name"
                            type="text"
                            value={form.display_name}
                            onInput={(e) => updateField('display_name', e.target.value)}
                            disabled={authLoading.value}
                            autoComplete="name"
                        />
                    </div>
                );
            case 'first_name':
                return (
                    <div className="space-y-2" key="first_name">
                        <Label for="wsms-reg-first-name">First Name</Label>
                        <Input
                            ref={isFirst ? firstFieldRef : undefined}
                            id="wsms-reg-first-name"
                            type="text"
                            value={form.first_name}
                            onInput={(e) => updateField('first_name', e.target.value)}
                            disabled={authLoading.value}
                            autoComplete="given-name"
                        />
                    </div>
                );
            case 'last_name':
                return (
                    <div className="space-y-2" key="last_name">
                        <Label for="wsms-reg-last-name">Last Name</Label>
                        <Input
                            ref={isFirst ? firstFieldRef : undefined}
                            id="wsms-reg-last-name"
                            type="text"
                            value={form.last_name}
                            onInput={(e) => updateField('last_name', e.target.value)}
                            disabled={authLoading.value}
                            autoComplete="family-name"
                        />
                    </div>
                );
            case 'email':
                return (
                    <div className="space-y-2" key="email">
                        <Label for="wsms-reg-email">Email</Label>
                        <Input
                            ref={isFirst ? firstFieldRef : undefined}
                            id="wsms-reg-email"
                            type="email"
                            value={form.email}
                            onInput={(e) => updateField('email', e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="email"
                        />
                    </div>
                );
            case 'phone':
                return (
                    <div className="space-y-2" key="phone">
                        <Label>Phone Number</Label>
                        <PhoneInput
                            value={form.phone}
                            onChange={(val) => updateField('phone', val)}
                            disabled={authLoading.value}
                            autoFocus={isFirst}
                        />
                    </div>
                );
            case 'password':
                return (
                    <div className="space-y-2" key="password">
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
                );
            default:
                return null;
        }
    }

    return (
        <AuthLayout
            title={title}
            footer={<AuthLink href={authUrl('/login')}>Already have an account? Sign in</AuthLink>}
        >
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="mb-4" />

            {socialPos === 'top' && socialBlock}

            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Render fields in definition order (respects sort_order from API) */}
                {fieldDefs.map((def, idx) => {
                    if (SYSTEM_FIELD_IDS.includes(def.id)) {
                        return renderSystemField(def.id, idx === 0);
                    }
                    return (
                        <DynamicField
                            key={def.id}
                            field={def}
                            value={form[def.id]}
                            onChange={(val) => updateField(def.id, val)}
                            disabled={authLoading.value}
                        />
                    );
                })}

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
                {legalLinks.value && (legalLinks.value.terms_url || legalLinks.value.privacy_url) && (
                    <p className="text-center text-xs text-muted-foreground">
                        {'By creating an account, you agree to our '}
                        {legalLinks.value.terms_url && (
                            <a href={legalLinks.value.terms_url} target="_blank" rel="noopener noreferrer" className="underline hover:text-foreground">
                                Terms of Service
                            </a>
                        )}
                        {legalLinks.value.terms_url && legalLinks.value.privacy_url && ' and '}
                        {legalLinks.value.privacy_url && (
                            <a href={legalLinks.value.privacy_url} target="_blank" rel="noopener noreferrer" className="underline hover:text-foreground">
                                Privacy Policy
                            </a>
                        )}
                        .
                    </p>
                )}
            </form>

            {socialPos === 'bottom' && socialBlock}
        </AuthLayout>
    );
}
