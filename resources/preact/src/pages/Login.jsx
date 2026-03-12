import { useState } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { api } from '../api/client';
import { primaryMethods } from '../signals/config';
import { authError, authLoading } from '../signals/auth';
import { handleAuthResponse, extractError } from '../utils/auth';
import { authUrl } from '../utils/urls';
import { AuthLayout } from '../layouts/AuthLayout';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { Label } from '../components/ui/Label';
import { AuthLink } from '../components/AuthLink';
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
                route(authUrl('/verify'));
            }
        } catch (err) {
            authError.value = extractError(err);
        } finally {
            authLoading.value = false;
        }
    }

    const footer = (
        <div className="flex gap-4">
            <AuthLink href={authUrl('/forgot-password')}>Forgot password?</AuthLink>
            <AuthLink href={authUrl('/register')}>Create account</AuthLink>
        </div>
    );

    return (
        <AuthLayout title="Sign In" footer={footer}>
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="mb-4" />
            <Alert variant="success" message={successMsg} className="mb-4" />

            <MethodSelector methods={methods} active={activeMethod} onChange={setActiveMethod} />

            {activeMethod === 'password' && (
                <form onSubmit={handlePasswordLogin} className="space-y-4">
                    <div className="space-y-2">
                        <Label for="wsms-username">Username or Email</Label>
                        <Input
                            id="wsms-username"
                            type="text"
                            value={username}
                            onInput={(e) => setUsername(e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="username"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label for="wsms-password">Password</Label>
                        <Input
                            id="wsms-password"
                            type="password"
                            value={password}
                            onInput={(e) => setPassword(e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="current-password"
                        />
                    </div>
                    <Button className="w-full" type="submit" disabled={authLoading.value}>
                        {authLoading.value ? 'Signing in\u2026' : 'Sign In'}
                    </Button>
                </form>
            )}

            {activeMethod === 'phone_otp' && (
                <form onSubmit={handlePasswordless} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Phone Number</Label>
                        <PhoneInput value={identifier} onChange={setIdentifier} disabled={authLoading.value} />
                    </div>
                    <Button className="w-full" type="submit" disabled={authLoading.value}>
                        {authLoading.value ? 'Sending\u2026' : 'Send OTP'}
                    </Button>
                </form>
            )}

            {(activeMethod === 'email_otp' || activeMethod === 'magic_link') && (
                <form onSubmit={handlePasswordless} className="space-y-4">
                    <div className="space-y-2">
                        <Label for="wsms-identifier">Email</Label>
                        <Input
                            id="wsms-identifier"
                            type="email"
                            value={identifier}
                            onInput={(e) => setIdentifier(e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="email"
                        />
                    </div>
                    <Button className="w-full" type="submit" disabled={authLoading.value}>
                        {authLoading.value ? 'Sending\u2026' : (activeMethod === 'magic_link' ? 'Send Magic Link' : 'Send OTP')}
                    </Button>
                </form>
            )}
        </AuthLayout>
    );
}
