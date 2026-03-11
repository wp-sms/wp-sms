import { useState } from 'preact/hooks';
import { api } from '../api/client';
import { registrationFields } from '../signals/config';
import { authError, authLoading } from '../signals/auth';
import { extractError } from '../utils/auth';
import { authUrl } from '../utils/urls';
import { Alert } from '../components/Alert';
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
            <div class="wsms-page">
                <h1 class="wsms-title">Account Created</h1>
                <Alert type="success" message={success} />
                <div class="wsms-links">
                    <a href={authUrl('/login')} class="wsms-link">Back to login</a>
                </div>
            </div>
        );
    }

    return (
        <div class="wsms-page">
            <h1 class="wsms-title">Create Account</h1>

            <Alert type="error" message={authError.value} onDismiss={() => (authError.value = null)} />

            <form onSubmit={handleSubmit} class="wsms-form">
                {fields.includes('username') && (
                    <div class="wsms-field">
                        <label class="wsms-label" for="wsms-reg-username">Username</label>
                        <input
                            id="wsms-reg-username"
                            class="wsms-input"
                            type="text"
                            value={form.username}
                            onInput={(e) => updateField('username', e.target.value)}
                            disabled={authLoading.value}
                            autoComplete="username"
                        />
                    </div>
                )}

                {fields.includes('display_name') && (
                    <div class="wsms-field">
                        <label class="wsms-label" for="wsms-reg-name">Display Name</label>
                        <input
                            id="wsms-reg-name"
                            class="wsms-input"
                            type="text"
                            value={form.display_name}
                            onInput={(e) => updateField('display_name', e.target.value)}
                            disabled={authLoading.value}
                            autoComplete="name"
                        />
                    </div>
                )}

                <div class="wsms-field">
                    <label class="wsms-label" for="wsms-reg-email">Email</label>
                    <input
                        id="wsms-reg-email"
                        class="wsms-input"
                        type="email"
                        value={form.email}
                        onInput={(e) => updateField('email', e.target.value)}
                        required
                        disabled={authLoading.value}
                        autoComplete="email"
                    />
                </div>

                {fields.includes('phone') && (
                    <div class="wsms-field">
                        <label class="wsms-label">Phone Number</label>
                        <PhoneInput
                            value={form.phone}
                            onChange={(val) => updateField('phone', val)}
                            disabled={authLoading.value}
                        />
                    </div>
                )}

                {fields.includes('password') && (
                    <div class="wsms-field">
                        <label class="wsms-label" for="wsms-reg-password">Password</label>
                        <input
                            id="wsms-reg-password"
                            class="wsms-input"
                            type="password"
                            value={form.password}
                            onInput={(e) => updateField('password', e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="new-password"
                        />
                    </div>
                )}

                <button class="wsms-btn wsms-btn--primary" type="submit" disabled={authLoading.value}>
                    {authLoading.value ? 'Creating account\u2026' : 'Create Account'}
                </button>
            </form>

            <div class="wsms-links">
                <a href={authUrl('/login')} class="wsms-link">Already have an account? Sign in</a>
            </div>
        </div>
    );
}
