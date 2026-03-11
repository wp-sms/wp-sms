import { useState } from 'preact/hooks';
import { api } from '../api/client';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { extractError } from '../utils/auth';
import { authUrl } from '../utils/urls';
import { Alert } from '../components/Alert';

export function ChangePassword() {
    const authed = useAuthGuard();
    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirm, setConfirm] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    async function handleSubmit(e) {
        e.preventDefault();
        setError('');
        setSuccess('');

        if (newPassword !== confirm) {
            setError('Passwords do not match.');
            return;
        }

        setLoading(true);

        try {
            const res = await api.put('/auth/password', {
                current_password: currentPassword,
                new_password: newPassword,
            });
            if (res.success) {
                setSuccess(res.message || 'Password changed successfully.');
                setCurrentPassword('');
                setNewPassword('');
                setConfirm('');
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
            <h1 class="wsms-title">Change Password</h1>

            <Alert type="error" message={error} onDismiss={() => setError('')} />
            <Alert type="success" message={success} />

            <form onSubmit={handleSubmit} class="wsms-form">
                <div class="wsms-field">
                    <label class="wsms-label" for="wsms-cur-pass">Current Password</label>
                    <input
                        id="wsms-cur-pass"
                        class="wsms-input"
                        type="password"
                        value={currentPassword}
                        onInput={(e) => setCurrentPassword(e.target.value)}
                        required
                        disabled={loading}
                        autoComplete="current-password"
                    />
                </div>

                <div class="wsms-field">
                    <label class="wsms-label" for="wsms-new-pass2">New Password</label>
                    <input
                        id="wsms-new-pass2"
                        class="wsms-input"
                        type="password"
                        value={newPassword}
                        onInput={(e) => setNewPassword(e.target.value)}
                        required
                        disabled={loading}
                        autoComplete="new-password"
                    />
                </div>

                <div class="wsms-field">
                    <label class="wsms-label" for="wsms-confirm-pass2">Confirm New Password</label>
                    <input
                        id="wsms-confirm-pass2"
                        class="wsms-input"
                        type="password"
                        value={confirm}
                        onInput={(e) => setConfirm(e.target.value)}
                        required
                        disabled={loading}
                        autoComplete="new-password"
                    />
                </div>

                <button class="wsms-btn wsms-btn--primary" type="submit" disabled={loading}>
                    {loading ? 'Changing\u2026' : 'Change Password'}
                </button>
            </form>

            <div class="wsms-links">
                <a href={authUrl('/')} class="wsms-link">Back to account</a>
            </div>
        </div>
    );
}
