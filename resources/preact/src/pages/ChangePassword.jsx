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
import { Input } from '../components/ui/Input';
import { Label } from '../components/ui/Label';

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
            setError(extractError(err));
        } finally {
            setLoading(false);
        }
    }

    if (!authed) return null;

    return (
        <AccountLayout title={title} currentPath="/change-password">
            <Alert variant="destructive" message={error} onDismiss={() => setError('')} className="mb-4" />
            <Alert variant="success" message={success} className="mb-4" />

            <form onSubmit={handleSubmit} className="space-y-4">
                {hasPassword && (
                    <div className="space-y-2">
                        <Label for="wsms-cur-pass">Current Password</Label>
                        <Input
                            ref={currentPasswordRef}
                            id="wsms-cur-pass"
                            type="password"
                            value={currentPassword}
                            onInput={(e) => setCurrentPassword(e.target.value)}
                            required
                            disabled={loading}
                            autoComplete="current-password"
                        />
                    </div>
                )}

                <div className="space-y-2">
                    <Label for="wsms-new-pass2">New Password</Label>
                    <Input
                        ref={newPasswordRef}
                        id="wsms-new-pass2"
                        type="password"
                        value={newPassword}
                        onInput={(e) => setNewPassword(e.target.value)}
                        required
                        disabled={loading}
                        autoComplete="new-password"
                    />
                </div>

                <div className="space-y-2">
                    <Label for="wsms-confirm-pass2">Confirm New Password</Label>
                    <Input
                        id="wsms-confirm-pass2"
                        type="password"
                        value={confirm}
                        onInput={(e) => setConfirm(e.target.value)}
                        required
                        disabled={loading}
                        autoComplete="new-password"
                    />
                </div>

                <Button className="w-full" type="submit" disabled={loading}>
                    {loading ? (hasPassword ? 'Changing\u2026' : 'Setting\u2026') : title}
                </Button>
            </form>
        </AccountLayout>
    );
}
