import { useState, useEffect } from 'preact/hooks';
import { useAutoFocus } from '../hooks/useAutoFocus';
import { api } from '../api/client';
import { currentUser } from '../signals/auth';
import { loadCurrentUser } from '../signals/user';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { extractError } from '../utils/auth';
import { AccountLayout } from '../layouts/AccountLayout';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { FormField } from '../components/ui/FormField';

export function ChangePassword() {
    const authed = useAuthGuard();
    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirm, setConfirm] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    useEffect(() => {
        if (authed && !currentUser.value) loadCurrentUser();
    }, [authed]);

    const user = currentUser.value;
    const hasPassword = user?.has_usable_password !== false;
    const currentPasswordRef = useAutoFocus(hasPassword);
    const newPasswordRef = useAutoFocus(!hasPassword);
    const title = hasPassword ? 'Change Password' : 'Set Password';

    async function handleSubmit(e) {
        e.preventDefault();
        setError('');
        setSuccess('');

        if (newPassword !== confirm) {
            setError('Passwords do not match.');
            return;
        }
        if (newPassword.length < 8) {
            setError('Password must be at least 8 characters.');
            return;
        }

        setLoading(true);

        try {
            const payload = { new_password: newPassword };
            if (hasPassword) {
                payload.current_password = currentPassword;
            }

            const res = await api.put('/auth/password', payload);
            if (res.success) {
                setSuccess(res.message || 'Password changed successfully.');
                setCurrentPassword('');
                setNewPassword('');
                setConfirm('');
            }
        } catch (err) {
            setError(extractError(err).message);
        } finally {
            setLoading(false);
        }
    }

    if (!authed) return null;

    return (
        <AccountLayout title={title} currentPath="/change-password">
            <Alert variant="destructive" message={error} onDismiss={() => setError('')} className="wsms-auth-mb-4" />
            <Alert variant="success" message={success} className="wsms-auth-mb-4" />

            <form onSubmit={handleSubmit} className="wsms-auth-stack-4">
                {hasPassword && (
                    <FormField
                        label="Current Password"
                        id="wsms-cur-pass"
                        type="password"
                        ref={currentPasswordRef}
                        value={currentPassword}
                        onInput={(e) => setCurrentPassword(e.target.value)}
                        required
                        disabled={loading}
                        autoComplete="current-password"
                        validate={FormField.validators.required}
                    />
                )}

                <FormField
                    label="New Password"
                    id="wsms-new-pass2"
                    type="password"
                    ref={newPasswordRef}
                    value={newPassword}
                    onInput={(e) => setNewPassword(e.target.value)}
                    required
                    disabled={loading}
                    autoComplete="new-password"
                    validate={FormField.validators.minLength(8)}
                />

                <FormField
                    label="Confirm New Password"
                    id="wsms-confirm-pass2"
                    type="password"
                    value={confirm}
                    onInput={(e) => setConfirm(e.target.value)}
                    required
                    disabled={loading}
                    autoComplete="new-password"
                    validate={FormField.validators.match(() => newPassword, 'Passwords')}
                />

                <Button className="wsms-auth-full" type="submit" loading={loading}>
                    {loading ? (hasPassword ? 'Changing\u2026' : 'Setting\u2026') : title}
                </Button>
            </form>
        </AccountLayout>
    );
}
