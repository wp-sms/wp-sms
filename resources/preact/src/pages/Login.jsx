import { useState } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { api } from '../api/client';
import { primaryMethods } from '../signals/config';
import { authError, authLoading } from '../signals/auth';
import { handleAuthResponse, extractError } from '../utils/auth';
import { authUrl } from '../utils/urls';
import { Alert } from '../components/Alert';
import { MethodSelector } from '../components/MethodSelector';
import { PhoneInput } from '../components/PhoneInput';

export function Login() {
    const { route } = useLocation();
    const methods = primaryMethods.value;
    const [activeMethod, setActiveMethod] = useState(methods[0] || 'password');
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [identifier, setIdentifier] = useState('');
    const [successMsg, setSuccessMsg] = useState('');

    async function handlePasswordLogin(e) {
        e.preventDefault();
        authError.value = null;
        authLoading.value = true;

        try {
            const res = await api.post('/auth/login', { username, password });
            handleAuthResponse(res, route);
        } catch (err) {
            authError.value = extractError(err);
        } finally {
            authLoading.value = false;
        }
    }

    async function handlePasswordless(e) {
        e.preventDefault();
        authError.value = null;
        authLoading.value = true;

        try {
            const res = await api.post('/auth/login/passwordless', {
                method: activeMethod,
                identifier,
            });

            const status = handleAuthResponse(res, route);

            if (status === 'challenge_sent' && activeMethod === 'magic_link') {
                setSuccessMsg('Check your email for a magic link.');
            } else if (status === 'challenge_sent') {
                route('/verify');
            }
        } catch (err) {
            authError.value = extractError(err);
        } finally {
            authLoading.value = false;
        }
    }

    return (
        <div class="wsms-page">
            <h1 class="wsms-title">Sign In</h1>

            <Alert type="error" message={authError.value} onDismiss={() => (authError.value = null)} />
            <Alert type="success" message={successMsg} />

            <MethodSelector methods={methods} active={activeMethod} onChange={setActiveMethod} />

            {activeMethod === 'password' && (
                <form onSubmit={handlePasswordLogin} class="wsms-form">
                    <div class="wsms-field">
                        <label class="wsms-label" for="wsms-username">Username or Email</label>
                        <input
                            id="wsms-username"
                            class="wsms-input"
                            type="text"
                            value={username}
                            onInput={(e) => setUsername(e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="username"
                        />
                    </div>
                    <div class="wsms-field">
                        <label class="wsms-label" for="wsms-password">Password</label>
                        <input
                            id="wsms-password"
                            class="wsms-input"
                            type="password"
                            value={password}
                            onInput={(e) => setPassword(e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="current-password"
                        />
                    </div>
                    <button class="wsms-btn wsms-btn--primary" type="submit" disabled={authLoading.value}>
                        {authLoading.value ? 'Signing in\u2026' : 'Sign In'}
                    </button>
                </form>
            )}

            {activeMethod === 'phone_otp' && (
                <form onSubmit={handlePasswordless} class="wsms-form">
                    <div class="wsms-field">
                        <label class="wsms-label">Phone Number</label>
                        <PhoneInput value={identifier} onChange={setIdentifier} disabled={authLoading.value} />
                    </div>
                    <PasswordlessSubmit loading={authLoading.value} label="Send OTP" />
                </form>
            )}

            {(activeMethod === 'email_otp' || activeMethod === 'magic_link') && (
                <form onSubmit={handlePasswordless} class="wsms-form">
                    <div class="wsms-field">
                        <label class="wsms-label" for="wsms-identifier">Email</label>
                        <input
                            id="wsms-identifier"
                            class="wsms-input"
                            type="email"
                            value={identifier}
                            onInput={(e) => setIdentifier(e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="email"
                        />
                    </div>
                    <PasswordlessSubmit
                        loading={authLoading.value}
                        label={activeMethod === 'magic_link' ? 'Send Magic Link' : 'Send OTP'}
                    />
                </form>
            )}

            <div class="wsms-links">
                <a href={authUrl('/forgot-password')} class="wsms-link">Forgot password?</a>
                <a href={authUrl('/register')} class="wsms-link">Create account</a>
            </div>
        </div>
    );
}

function PasswordlessSubmit({ loading, label }) {
    return (
        <button class="wsms-btn wsms-btn--primary" type="submit" disabled={loading}>
            {loading ? 'Sending\u2026' : label}
        </button>
    );
}
