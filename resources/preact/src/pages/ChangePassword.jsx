import { useState } from 'preact/hooks';
import { api } from '../api/client';
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
        <AccountLayout title="Change Password" currentPath="/change-password">
            <Alert variant="destructive" message={error} onDismiss={() => setError('')} className="mb-4" />
            <Alert variant="success" message={success} className="mb-4" />

            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="space-y-2">
                    <Label for="wsms-cur-pass">Current Password</Label>
                    <Input
                        id="wsms-cur-pass"
                        type="password"
                        value={currentPassword}
                        onInput={(e) => setCurrentPassword(e.target.value)}
                        required
                        disabled={loading}
                        autoComplete="current-password"
                    />
                </div>

                <div className="space-y-2">
                    <Label for="wsms-new-pass2">New Password</Label>
                    <Input
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
                    {loading ? 'Changing\u2026' : 'Change Password'}
                </Button>
            </form>
        </AccountLayout>
    );
}
