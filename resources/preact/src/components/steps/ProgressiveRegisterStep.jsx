import { useState } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import { useAutoFocus } from '../../hooks/useAutoFocus';
import { api } from '../../api/client';
import {
    authError,
    authLoading,
    authStep,
    identifyResult,
    enteredIdentifier,
    registrationToken,
    pendingVerifications,
    resetIdentifyFlow,
} from '../../signals/auth';
import { extractError } from '../../utils/auth';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { Input } from '../ui/Input';
import { PasswordInput } from '../ui/PasswordInput';
import { Label } from '../ui/Label';
import { PhoneInput } from '../PhoneInput';

export function ProgressiveRegisterStep() {
    const result = identifyResult.value;
    const identifier = enteredIdentifier.value;
    const identifierType = result?.identifier_type;
    const fields = result?.registration_fields || ['email', 'password'];
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
            const res = await api.post('/auth/register', body);
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
        } finally {
            authLoading.value = false;
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

    return (
        <div className="wsms-auth-stack-4 wsms-auth-fade-in">
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="wsms-auth-mb-4" />

            <p className="wsms-auth-text-sm wsms-auth-text-muted wsms-auth-center">
                {__('Create your account to get started', 'wp-sms')}
            </p>

            <form onSubmit={handleSubmit} className="wsms-auth-stack-4">
                {fields.includes('username') && (
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-reg-username">{__('Username', 'wp-sms')}</Label>
                        <Input
                            ref={firstEditableField === 'username' ? firstFieldRef : undefined}
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
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-reg-name">{__('Display Name', 'wp-sms')}</Label>
                        <Input
                            ref={firstEditableField === 'display_name' ? firstFieldRef : undefined}
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
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-reg-first-name">{__('First Name', 'wp-sms')}</Label>
                        <Input
                            ref={firstEditableField === 'first_name' ? firstFieldRef : undefined}
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
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-reg-last-name">{__('Last Name', 'wp-sms')}</Label>
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
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-reg-email">{__('Email', 'wp-sms')}</Label>
                        <Input
                            ref={firstEditableField === 'email' ? firstFieldRef : undefined}
                            id="wsms-reg-email"
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
                        <Label>{__('Phone Number', 'wp-sms')}</Label>
                        <PhoneInput
                            value={form.phone}
                            onChange={(val) => updateField('phone', val)}
                            disabled={authLoading.value || identifierType === 'phone'}
                            autoFocus={firstEditableField === 'phone'}
                        />
                    </div>
                )}

                {fields.includes('password') && (
                    <div className="wsms-auth-stack-2">
                        <Label for="wsms-reg-password">{__('Password', 'wp-sms')}</Label>
                        <PasswordInput
                            ref={firstEditableField === 'password' ? firstFieldRef : undefined}
                            id="wsms-reg-password"
                            value={form.password}
                            onInput={(e) => updateField('password', e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="new-password"
                        />
                    </div>
                )}

                <Button className="wsms-auth-full" type="submit" loading={authLoading.value}>
                    {authLoading.value ? __('Creating account...', 'wp-sms') : __('Create Account', 'wp-sms')}
                </Button>
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
