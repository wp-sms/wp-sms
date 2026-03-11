import { useState } from 'preact/hooks';
import { api } from '../api/client';
import { authError, authLoading } from '../signals/auth';
import { extractError } from '../utils/auth';
import { authUrl, getQueryParam } from '../utils/urls';
import { Alert } from '../components/Alert';

export function ResetPassword() {
    const token = getQueryParam('token');
    const [password, setPassword] = useState('');
    const [confirm, setConfirm] = useState('');
    const [success, setSuccess] = useState('');

    async function handleSubmit(e) {
        e.preventDefault();
        authError.value = null;

        if (password !== confirm) {
            authError.value = 'Passwords do not match.';
            return;
        }

        authLoading.value = true;

        try {
            const res = await api.post('/auth/reset-password', { token, password });
            if (res.success) {
                setSuccess(res.message || 'Password reset successfully.');
            }
        } catch (err) {
            authError.value = extractError(err);
        } finally {
            authLoading.value = false;
        }
    }

    if (!token) {
        return (
            <div class="wsms-page">
                <h1 class="wsms-title">Invalid Link</h1>
                <Alert type="error" message="No reset token found. Please request a new password reset." />
                <div class="wsms-links">
                    <a href={authUrl('/forgot-password')} class="wsms-link">Request reset link</a>
                </div>
            </div>
        );
    }

    if (success) {
        return (
            <div class="wsms-page">
                <h1 class="wsms-title">Password Reset</h1>
                <Alert type="success" message={success} />
                <div class="wsms-links">
                    <a href={authUrl('/login')} class="wsms-link">Sign in with new password</a>
                </div>
            </div>
        );
    }

    return (
        <div class="wsms-page">
            <h1 class="wsms-title">Reset Password</h1>

            <Alert type="error" message={authError.value} onDismiss={() => (authError.value = null)} />

            <form onSubmit={handleSubmit} class="wsms-form">
                <div class="wsms-field">
                    <label class="wsms-label" for="wsms-new-pass">New Password</label>
                    <input
                        id="wsms-new-pass"
                        class="wsms-input"
                        type="password"
                        value={password}
                        onInput={(e) => setPassword(e.target.value)}
                        required
                        disabled={authLoading.value}
                        autoComplete="new-password"
                    />
                </div>
                <div class="wsms-field">
                    <label class="wsms-label" for="wsms-confirm-pass">Confirm Password</label>
                    <input
                        id="wsms-confirm-pass"
                        class="wsms-input"
                        type="password"
                        value={confirm}
                        onInput={(e) => setConfirm(e.target.value)}
                        required
                        disabled={authLoading.value}
                        autoComplete="new-password"
                    />
                </div>
                <button class="wsms-btn wsms-btn--primary" type="submit" disabled={authLoading.value}>
                    {authLoading.value ? 'Resetting\u2026' : 'Reset Password'}
                </button>
            </form>
        </div>
    );
}
