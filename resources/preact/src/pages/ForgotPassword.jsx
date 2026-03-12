import { useState } from 'preact/hooks';
import { api } from '../api/client';
import { authError, authLoading } from '../signals/auth';
import { extractError } from '../utils/auth';
import { authUrl } from '../utils/urls';
import { AuthLayout } from '../layouts/AuthLayout';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { Label } from '../components/ui/Label';
import { AuthLink } from '../components/AuthLink';

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
        <AuthLayout
            title="Forgot Password"
            subtitle="Enter your email and we'll send you a reset link."
            footer={<AuthLink href={authUrl('/login')}>Back to login</AuthLink>}
        >
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="mb-4" />
            <Alert variant="success" message={success} className="mb-4" />

            {!success && (
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label for="wsms-forgot-email">Email</Label>
                        <Input
                            id="wsms-forgot-email"
                            type="email"
                            value={email}
                            onInput={(e) => setEmail(e.target.value)}
                            required
                            disabled={authLoading.value}
                            autoComplete="email"
                        />
                    </div>
                    <Button className="w-full" type="submit" disabled={authLoading.value}>
                        {authLoading.value ? 'Sending\u2026' : 'Send Reset Link'}
                    </Button>
                </form>
            )}
        </AuthLayout>
    );
}
