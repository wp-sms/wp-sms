import { useState, useEffect } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import { useAutoFocus } from '../../hooks/useAutoFocus';
import { api } from '../../api/client';
import {
    authError,
    authLoading,
    stopLoading,
    authStep,
    identifyResult,
    enteredIdentifier,
    registrationToken,
    pendingVerifications,
    resetIdentifyFlow,
} from '../../signals/auth';
import { registrationFieldDefs, formSlug } from '../../signals/config';
import { extractError } from '../../utils/auth';
import { SYSTEM_FIELD_IDS } from '../../utils/fields';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { Input } from '../ui/Input';
import { PasswordInput } from '../ui/PasswordInput';
import { Label } from '../ui/Label';
import { PhoneInput } from '../PhoneInput';
import { DynamicField } from '../DynamicField';
import { CaptchaWidget } from '../CaptchaWidget';
import { useCaptcha } from '../../hooks/useCaptcha';
import { LegalLinks } from '../LegalLinks';

export function ProgressiveRegisterStep() {
    const result = identifyResult.value;
    const identifier = enteredIdentifier.value;
    const identifierType = result?.identifier_type;
    const fields = result?.registration_fields || ['email', 'password'];
    const fieldDefs = registrationFieldDefs.value;
    const customFieldDefs = fieldDefs.filter((def) => !SYSTEM_FIELD_IDS.includes(def.id) && fields.includes(def.id));
    const captcha = useCaptcha();
    const needsCaptcha = captcha.isRequiredFor('register');

    const firstEditableField = fields.find((f) =>
        (f === 'email' && identifierType !== 'email') ||
        (f === 'phone' && identifierType !== 'phone') ||
        (f !== 'email' && f !== 'phone')
    );
    const firstFieldRef = useAutoFocus();

    const [form, setForm] = useState(() => {
        const initial = { email: '', password: '', username: '', display_name: '', first_name: '', last_name: '', phone: '' };
        // Pre-fill the identifier field.
        if (identifierType === 'email') initial.email = identifier;
        if (identifierType === 'phone') initial.phone = identifier;
        return initial;
    });
    const [success, setSuccess] = useState('');

    // Initialize custom field defaults when defs load.
    useEffect(() => {
        if (customFieldDefs.length > 0) {
            const defaults = {};
            for (const def of customFieldDefs) {
                if (!(def.id in form)) {
                    if (def.default_value !== undefined && def.default_value !== '') {
                        defaults[def.id] = def.default_value;
                    } else {
                        defaults[def.id] = def.type === 'checkbox' ? false : '';
                    }
                }
            }
            if (Object.keys(defaults).length > 0) {
                setForm((prev) => ({ ...prev, ...defaults }));
            }
        }
    }, [fieldDefs]); // eslint-disable-line react-hooks/exhaustive-deps

    function updateField(name, value) {
        setForm((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        authError.value = null;
        authLoading.value = true;

        const body = {};
        for (const f of fields) {
            if (form[f] !== undefined && form[f] !== '') body[f] = form[f];
        }
        // Include custom fields.
        for (const def of customFieldDefs) {
            if (form[def.id] !== undefined) {
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
                    authStep.value = 'register_verify';
                } else {
                    setSuccess(res.message || __('Account created successfully.', 'wp-sms'));
                }
            }
        } catch (err) {
            authError.value = extractError(err).message;
            captcha.reset();
        } finally {
            stopLoading();
        }
    }

    if (success) {
        return (
            <div className="wsms-auth-stack-4 wsms-auth-fade-in">
                <Alert variant="success" message={success} />
                <div className="wsms-auth-center">
                    <Button variant="link" type="button" onClick={resetIdentifyFlow}>
                        {__('Back to sign in', 'wp-sms')}
                    </Button>
                </div>
            </div>
        );
    }

    const hasFirstName = fields.includes('first_name');
    const hasLastName = fields.includes('last_name');
    const hasNamePair = hasFirstName && hasLastName;

    return (
        <div className="wsms-auth-stack-4 wsms-auth-fade-in">
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="wsms-auth-mb-4" />

            <p className="wsms-auth-text-sm wsms-auth-text-muted wsms-auth-center">
                {__('Create your account to get started', 'wp-sms')}
            </p>

            <form onSubmit={handleSubmit} className="wsms-auth-stack-4">
                {fields.includes('username') && (
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-prog-username">{__('Username', 'wp-sms')}</Label>
                        <Input
                            ref={firstEditableField === 'username' ? firstFieldRef : undefined}
                            id="wsms-prog-username"
                            type="text"
                            value={form.username}
                            onInput={(e) => updateField('username', e.target.value)}
                            disabled={authLoading.value}
                            autoComplete="username"
                        />
                    </div>
                )}

                {fields.includes('display_name') && (
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-prog-name">{__('Display Name', 'wp-sms')}</Label>
                        <Input
                            ref={firstEditableField === 'display_name' ? firstFieldRef : undefined}
                            id="wsms-prog-name"
                            type="text"
                            value={form.display_name}
                            onInput={(e) => updateField('display_name', e.target.value)}
                            disabled={authLoading.value}
                            autoComplete="name"
                        />
                    </div>
                )}

                {hasNamePair ? (
                    <div className="wsms-auth-profile-grid">
                        <div className="wsms-auth-stack-2">
                            <Label for="wsms-prog-first-name">{__('First Name', 'wp-sms')}</Label>
                            <Input
                                ref={firstEditableField === 'first_name' ? firstFieldRef : undefined}
                                id="wsms-prog-first-name"
                                type="text"
                                value={form.first_name}
                                onInput={(e) => updateField('first_name', e.target.value)}
                                disabled={authLoading.value}
                                autoComplete="given-name"
                            />
                        </div>
                        <div className="wsms-auth-stack-2">
                            <Label for="wsms-prog-last-name">{__('Last Name', 'wp-sms')}</Label>
                            <Input
                                id="wsms-prog-last-name"
                                type="text"
                                value={form.last_name}
                                onInput={(e) => updateField('last_name', e.target.value)}
                                disabled={authLoading.value}
                                autoComplete="family-name"
                            />
                        </div>
                    </div>
                ) : (
                    <>
                        {hasFirstName && (
                            <div className="wsms-auth-stack-2">
                                <Label for="wsms-prog-first-name">{__('First Name', 'wp-sms')}</Label>
                                <Input
                                    ref={firstEditableField === 'first_name' ? firstFieldRef : undefined}
                                    id="wsms-prog-first-name"
                                    type="text"
                                    value={form.first_name}
                                    onInput={(e) => updateField('first_name', e.target.value)}
                                    disabled={authLoading.value}
                                    autoComplete="given-name"
                                />
                            </div>
                        )}
                        {hasLastName && (
                            <div className="wsms-auth-stack-2">
                                <Label for="wsms-prog-last-name">{__('Last Name', 'wp-sms')}</Label>
                                <Input
                                    id="wsms-prog-last-name"
                                    type="text"
                                    value={form.last_name}
                                    onInput={(e) => updateField('last_name', e.target.value)}
                                    disabled={authLoading.value}
                                    autoComplete="family-name"
                                />
                            </div>
                        )}
                    </>
                )}

                {fields.includes('email') && (
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-prog-email">{__('Email', 'wp-sms')}</Label>
                        <Input
                            ref={firstEditableField === 'email' ? firstFieldRef : undefined}
                            id="wsms-prog-email"
                            type="email"
                            value={form.email}
                            onInput={(e) => updateField('email', e.target.value)}
                            required
                            disabled={authLoading.value || identifierType === 'email'}
                            autoComplete="email"
                        />
                    </div>
                )}

                {fields.includes('phone') && (
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-prog-phone">{__('Phone Number', 'wp-sms')}</Label>
                        <PhoneInput
                            id="wsms-prog-phone"
                            value={form.phone}
                            onChange={(val) => updateField('phone', val)}
                            disabled={authLoading.value || identifierType === 'phone'}
                            autoFocus={firstEditableField === 'phone'}
                        />
                    </div>
                )}

                {fields.includes('password') && (
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-prog-password">{__('Password', 'wp-sms')}</Label>
                        <PasswordInput
                            ref={firstEditableField === 'password' ? firstFieldRef : undefined}
                            id="wsms-prog-password"
                            value={form.password}
                            onInput={(e) => updateField('password', e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="new-password"
                        />
                    </div>
                )}

                {customFieldDefs.map((def) => (
                    <DynamicField
                        key={def.id}
                        field={def}
                        value={form[def.id]}
                        onChange={(val) => updateField(def.id, val)}
                        disabled={authLoading.value}
                    />
                ))}

                {needsCaptcha && (
                    <CaptchaWidget
                        provider={captcha.provider}
                        siteKey={captcha.siteKey}
                        onVerify={captcha.setToken}
                        resetRef={captcha.resetRef}
                    />
                )}

                <Button className="wsms-auth-full" type="submit" loading={authLoading.value} disabled={needsCaptcha && !captcha.token}>
                    {authLoading.value ? __('Creating account...', 'wp-sms') : __('Create Account', 'wp-sms')}
                </Button>

                <LegalLinks variant="consent" />
            </form>

            <div className="wsms-auth-center">
                <Button variant="link" type="button" onClick={resetIdentifyFlow}>
                    {identifierType === 'email'
                        ? __('Use a different email', 'wp-sms')
                        : identifierType === 'phone'
                            ? __('Use a different phone', 'wp-sms')
                            : __('Use a different identifier', 'wp-sms')}
                </Button>
            </div>
        </div>
    );
}
