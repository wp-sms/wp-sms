import { useState } from 'preact/hooks';
import { api } from '../../api/client';
import {
    authError,
    authLoading,
    identifyResult,
    enteredIdentifier,
    resetIdentifyFlow,
} from '../../signals/auth';
import { extractError } from '../../utils/auth';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { Input } from '../ui/Input';
import { Label } from '../ui/Label';
import { PhoneInput } from '../PhoneInput';

export function ProgressiveRegisterStep() {
    const result = identifyResult.value;
    const identifier = enteredIdentifier.value;
    const identifierType = result?.identifier_type;
    const fields = result?.registration_fields || ['email', 'password'];

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
                setSuccess(res.message || 'Account created successfully.');
            }
        } catch (err) {
            authError.value = extractError(err);
        } finally {
            authLoading.value = false;
        }
    }

    if (success) {
        return (
            <div className="space-y-4 animate-fade-in">
                <Alert variant="success" message={success} />
                <div className="text-center">
                    <Button variant="link" type="button" onClick={resetIdentifyFlow}>
                        Back to sign in
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-4 animate-fade-in">
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="mb-4" />

            <p className="text-sm text-muted-foreground text-center">
                Create your account to get started
            </p>

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

                <div className="space-y-2">
                    <Label for="wsms-reg-email">Email</Label>
                    <Input
                        id="wsms-reg-email"
                        type="email"
                        value={form.email}
                        onInput={(e) => updateField('email', e.target.value)}
                        required
                        disabled={authLoading.value || identifierType === 'email'}
                        autoComplete="email"
                    />
                </div>

                {fields.includes('phone') && (
                    <div className="space-y-2">
                        <Label>Phone Number</Label>
                        <PhoneInput
                            value={form.phone}
                            onChange={(val) => updateField('phone', val)}
                            disabled={authLoading.value || identifierType === 'phone'}
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

                <Button className="w-full" type="submit" disabled={authLoading.value}>
                    {authLoading.value ? 'Creating account...' : 'Create Account'}
                </Button>
            </form>

            <div className="text-center">
                <Button variant="link" type="button" onClick={resetIdentifyFlow}>
                    Use a different {identifierType === 'email' ? 'email' : identifierType === 'phone' ? 'phone' : 'identifier'}
                </Button>
            </div>
        </div>
    );
}
