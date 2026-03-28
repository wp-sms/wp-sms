import { useState } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import { useAutoFocus } from '../hooks/useAutoFocus';
import { api } from '../api/client';
import { authError, authLoading } from '../signals/auth';
import { extractError } from '../utils/auth';
import { authUrl, getQueryParam } from '../utils/urls';
import { AuthLayout } from '../layouts/AuthLayout';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { FormField } from '../components/ui/FormField';
import { AuthLink } from '../components/AuthLink';

export function ResetPassword() {
    const token = getQueryParam('token');
    const [password, setPassword] = useState('');
    const [confirm, setConfirm] = useState('');
    const [success, setSuccess] = useState('');
    const passwordRef = useAutoFocus();

    async function handleSubmit(e) {
        e.preventDefault();
        authError.value = null;

        if (password !== confirm) {
            authError.value = __('Passwords do not match.', 'wp-sms');
            return;
        }

        authLoading.value = true;

        try {
            const res = await api.post('/auth/reset-password', { token, password });
            if (res.success) {
                setSuccess(res.message || __('Password reset successfully.', 'wp-sms'));
            }
        } catch (err) {
            authError.value = extractError(err).message;
        } finally {
            authLoading.value = false;
        }
    }

    if (!token) {
        return (
            <AuthLayout
                title={__('Invalid Link', 'wp-sms')}
                footer={<AuthLink href={authUrl('/forgot-password')}>{__('Request reset link', 'wp-sms')}</AuthLink>}
            >
                <Alert variant="destructive" message={__('No reset token found. Please request a new password reset.', 'wp-sms')} />
            </AuthLayout>
        );
    }

    if (success) {
        return (
            <AuthLayout
                title={__('Password Reset', 'wp-sms')}
                footer={<AuthLink href={authUrl('/login')}>{__('Sign in with new password', 'wp-sms')}</AuthLink>}
            >
                <Alert variant="success" message={success} />
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title={__('Reset Password', 'wp-sms')}>
            <Alert variant="destructive" message={authError.value} onDismiss={() => (authError.value = null)} className="wsms-auth-mb-4" />

            <form onSubmit={handleSubmit} className="wsms-auth-stack-4">
                <FormField
                    label={__('New Password', 'wp-sms')}
                    id="wsms-new-pass"
                    type="password"
                    ref={passwordRef}
                    value={password}
                    onInput={(e) => setPassword(e.target.value)}
                    required
                    disabled={authLoading.value}
                    autoComplete="new-password"
                    validate={FormField.validators.minLength(8)}
                />
                <FormField
                    label={__('Confirm Password', 'wp-sms')}
                    id="wsms-confirm-pass"
                    type="password"
                    value={confirm}
                    onInput={(e) => setConfirm(e.target.value)}
                    required
                    disabled={authLoading.value}
                    autoComplete="new-password"
                    validate={FormField.validators.match(() => password, __('Passwords', 'wp-sms'))}
                />
                <Button className="wsms-auth-full" type="submit" disabled={authLoading.value}>
                    {authLoading.value ? __('Resetting\u2026', 'wp-sms') : __('Reset Password', 'wp-sms')}
                </Button>
            </form>
        </AuthLayout>
    );
}
