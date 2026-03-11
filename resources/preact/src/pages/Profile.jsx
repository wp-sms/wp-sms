import { useState, useEffect } from 'preact/hooks';
import { api } from '../api/client';
import { currentUser } from '../signals/auth';
import { loadCurrentUser, refreshUser } from '../signals/user';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { extractError } from '../utils/auth';
import { authUrl } from '../utils/urls';
import { Alert } from '../components/Alert';
import { PhoneInput } from '../components/PhoneInput';

export function Profile() {
    const authed = useAuthGuard();
    const [form, setForm] = useState({ display_name: '', email: '', phone: '' });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    useEffect(() => {
        if (!authed) return;

        async function init() {
            if (!currentUser.value) await loadCurrentUser();
            const u = currentUser.value;
            if (u) {
                setForm({
                    display_name: u.display_name || '',
                    email: u.email || '',
                    phone: u.phone || '',
                });
            }
        }
        init();
    }, [authed]);

    function updateField(name, value) {
        setForm((prev) => ({ ...prev, [name]: value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();
        setError('');
        setSuccess('');
        setLoading(true);

        try {
            const res = await api.put('/auth/profile', form);
            if (res.success) {
                setSuccess(res.message || 'Profile updated.');
                await refreshUser();
            }
        } catch (err) {
            setError(extractError(err));
        } finally {
            setLoading(false);
        }
    }

    if (!authed) return null;

    return (
        <div class="wsms-page">
            <h1 class="wsms-title">Profile</h1>
            <p class="wsms-subtitle">Manage your account information</p>

            <Alert type="error" message={error} onDismiss={() => setError('')} />
            <Alert type="success" message={success} />

            <form onSubmit={handleSubmit} class="wsms-form">
                <div class="wsms-field">
                    <label class="wsms-label" for="wsms-prof-name">Display Name</label>
                    <input
                        id="wsms-prof-name"
                        class="wsms-input"
                        type="text"
                        value={form.display_name}
                        onInput={(e) => updateField('display_name', e.target.value)}
                        disabled={loading}
                        autoComplete="name"
                    />
                </div>

                <div class="wsms-field">
                    <label class="wsms-label" for="wsms-prof-email">Email</label>
                    <input
                        id="wsms-prof-email"
                        class="wsms-input"
                        type="email"
                        value={form.email}
                        onInput={(e) => updateField('email', e.target.value)}
                        required
                        disabled={loading}
                        autoComplete="email"
                    />
                </div>

                <div class="wsms-field">
                    <label class="wsms-label">Phone Number</label>
                    <PhoneInput
                        value={form.phone}
                        onChange={(val) => updateField('phone', val)}
                        disabled={loading}
                    />
                </div>

                <button class="wsms-btn wsms-btn--primary" type="submit" disabled={loading}>
                    {loading ? 'Saving\u2026' : 'Save Changes'}
                </button>
            </form>

            <div class="wsms-links">
                <a href={authUrl('/')} class="wsms-link">Back to account</a>
            </div>
        </div>
    );
}
