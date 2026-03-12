import { useState } from 'preact/hooks';
import { api } from '../api/client';
import { registrationFields } from '../signals/config';
import { authError, authLoading } from '../signals/auth';
import { extractError } from '../utils/auth';
import { authUrl } from '../utils/urls';
import { AuthLayout } from '../layouts/AuthLayout';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { Label } from '../components/ui/Label';
import { AuthLink } from '../components/AuthLink';
import { PhoneInput } from '../components/PhoneInput';

export function Register() {
    const fields = registrationFields.value;
    const [form, setForm] = useState({
        email: '',
        password: '',
        username: '',
        display_name: '',
        phone: '',
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
                setSuccess(res.message || 'Account created. Please check your email to verify.');
            }
        } catch (err) {
            authError.value = extractError(err);
        } finally {
            authLoading.value = false;
        }
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

                <Button className="w-full" type="submit" disabled={authLoading.value}>
                    {authLoading.value ? 'Creating account\u2026' : 'Create Account'}
                </Button>
            </form>
        </AuthLayout>
    );
}
