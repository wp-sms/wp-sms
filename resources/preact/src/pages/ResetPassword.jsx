import { useState } from 'preact/hooks';
import { api } from '../api/client';
import { authError, authLoading } from '../signals/auth';
import { extractError } from '../utils/auth';
import { authUrl, getQueryParam } from '../utils/urls';
import { AuthLayout } from '../layouts/AuthLayout';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { Label } from '../components/ui/Label';
import { AuthLink } from '../components/AuthLink';

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
            <AuthLayout
                title="Invalid Link"
                footer={<AuthLink href={authUrl('/forgot-password')}>Request reset link</AuthLink>}
            >
                <Alert variant="destructive" message="No reset token found. Please request a new password reset." />
            </AuthLayout>
        );
    }

    if (success) {
        return (
            <AuthLayout
                title="Password Reset"
                footer={<AuthLink href={authUrl('/login')}>Sign in with new password</AuthLink>}
            >
                <Alert variant="success" message={success} />
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title="Reset Password">
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="mb-4" />

            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="space-y-2">
                    <Label for="wsms-new-pass">New Password</Label>
                    <Input
                        id="wsms-new-pass"
                        type="password"
                        value={password}
                        onInput={(e) => setPassword(e.target.value)}
                        required
                        disabled={authLoading.value}
                        autoComplete="new-password"
                    />
                </div>
                <div className="space-y-2">
                    <Label for="wsms-confirm-pass">Confirm Password</Label>
                    <Input
                        id="wsms-confirm-pass"
                        type="password"
                        value={confirm}
                        onInput={(e) => setConfirm(e.target.value)}
                        required
                        disabled={authLoading.value}
                        autoComplete="new-password"
                    />
                </div>
                <Button className="w-full" type="submit" disabled={authLoading.value}>
                    {authLoading.value ? 'Resetting\u2026' : 'Reset Password'}
                </Button>
            </form>
        </AuthLayout>
    );
}
