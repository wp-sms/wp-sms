import { useState } from 'preact/hooks';
import { api } from '../api/client';
import { authError, authLoading } from '../signals/auth';
import { extractError } from '../utils/auth';
import { authUrl } from '../utils/urls';
import { Alert } from '../components/Alert';

export function ForgotPassword() {
    const [email, setEmail] = useState('');
    const [success, setSuccess] = useState('');

    async function handleSubmit(e) {
        e.preventDefault();
        authError.value = null;
        authLoading.value = true;

        try {
            const res = await api.post('/auth/forgot-password', { email });
            setSuccess(res.message || 'If that email exists, a reset link has been sent.');
        } catch (err) {
            authError.value = extractError(err);
        } finally {
            authLoading.value = false;
        }
    }

    return (
        <div class="wsms-page">
            <h1 class="wsms-title">Forgot Password</h1>
            <p class="wsms-subtitle">Enter your email and we'll send you a reset link.</p>

            <Alert type="error" message={authError.value} onDismiss={() => (authError.value = null)} />
            <Alert type="success" message={success} />

            {!success && (
                <form onSubmit={handleSubmit} class="wsms-form">
                    <div class="wsms-field">
                        <label class="wsms-label" for="wsms-forgot-email">Email</label>
                        <input
                            id="wsms-forgot-email"
                            class="wsms-input"
                            type="email"
                            value={email}
                            onInput={(e) => setEmail(e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="email"
                        />
                    </div>
                    <button class="wsms-btn wsms-btn--primary" type="submit" disabled={authLoading.value}>
                        {authLoading.value ? 'Sending\u2026' : 'Send Reset Link'}
                    </button>
                </form>
            )}

            <div class="wsms-links">
                <a href={authUrl('/login')} class="wsms-link">Back to login</a>
            </div>
        </div>
    );
}
