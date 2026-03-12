import { useState, useEffect } from 'preact/hooks';
import { api } from '../api/client';
import { currentUser } from '../signals/auth';
import { loadCurrentUser, refreshUser, enrolledFactors } from '../signals/user';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { extractError } from '../utils/auth';
import { AccountLayout } from '../layouts/AccountLayout';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { Spinner } from '../components/ui/Spinner';
import { Separator } from '../components/ui/Separator';
import { MfaFactorCard } from '../components/MfaFactorCard';
import { BackupCodesDisplay } from '../components/BackupCodesDisplay';

export function Security() {
    const authed = useAuthGuard();
    const [availableMethods, setAvailableMethods] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [backupCodes, setBackupCodes] = useState(null);

    useEffect(() => {
        if (!authed) return;
        init();
    }, [authed]);

    async function init() {
        setLoading(true);
        try {
            const [, methodsRes] = await Promise.all([
                currentUser.value ? Promise.resolve() : loadCurrentUser(),
                api.get('/auth/methods'),
            ]);
            setAvailableMethods(methodsRes.methods.filter((m) => m.supports_mfa));
        } catch (err) {
            setError(extractError(err));
        } finally {
            setLoading(false);
        }
    }

    function isEnrolled(channelId) {
        return enrolledFactors.value.some((f) => f.channel_id === channelId);
    }

    function getFactorInfo(channelId) {
        return enrolledFactors.value.find((f) => f.channel_id === channelId);
    }

    async function handleEnroll(channelId, data = {}) {
        setError('');
        setSuccess('');

        try {
            const res = await api.post('/auth/mfa/enroll', { channel_id: channelId, data });

            if (res.success) {
                setSuccess(res.message || `${channelId} enrolled.`);

                if (res.data?.backup_codes) {
                    setBackupCodes(res.data.backup_codes);
                }

                await refreshUser();
                return res;
            }
        } catch (err) {
            setError(extractError(err));
        }
        return null;
    }

    async function handleUnenroll(channelId) {
        setError('');
        setSuccess('');

        try {
            const res = await api.del('/auth/mfa/unenroll', { channel_id: channelId });
            if (res.success) {
                setSuccess(res.message || `${channelId} removed.`);
                await refreshUser();
            }
        } catch (err) {
            setError(extractError(err));
        }
    }

    async function handleRegenerateBackupCodes() {
        setError('');

        try {
            const res = await api.post('/auth/mfa/backup-codes/regenerate');
            if (res.success) {
                setBackupCodes(res.data?.codes || []);
                await refreshUser();
            }
        } catch (err) {
            setError(extractError(err));
        }
    }

    if (!authed) return null;

    if (loading) {
        return (
            <AccountLayout title="Security" currentPath="/security">
                <div className="flex flex-col items-center gap-3 py-8">
                    <Spinner className="size-8" />
                    <p className="text-sm text-muted-foreground">Loading security settings\u2026</p>
                </div>
            </AccountLayout>
        );
    }

    return (
        <AccountLayout title="Security" subtitle="Manage your multi-factor authentication methods" currentPath="/security">
            <Alert variant="destructive" message={error} onDismiss={() => setError('')} className="mb-4" />
            <Alert variant="success" message={success} className="mb-4" />

            {backupCodes && (
                <BackupCodesDisplay
                    codes={backupCodes}
                    onDismiss={() => setBackupCodes(null)}
                />
            )}

            <div className="space-y-3">
                {availableMethods.map((method) => (
                    <MfaFactorCard
                        key={method.id}
                        method={method}
                        enrolled={isEnrolled(method.id)}
                        info={getFactorInfo(method.id)}
                        onEnroll={handleEnroll}
                        onUnenroll={handleUnenroll}
                        onRefresh={refreshUser}
                    />
                ))}
            </div>

            {isEnrolled('backup_codes') && (
                <div className="mt-6 space-y-3">
                    <Separator />
                    <h3 className="text-base font-semibold">Backup Codes</h3>
                    <p className="text-sm text-muted-foreground">
                        {getFactorInfo('backup_codes')?.remaining_codes != null
                            ? `${getFactorInfo('backup_codes').remaining_codes} codes remaining`
                            : 'Backup codes are enabled'}
                    </p>
                    <Button variant="outline" onClick={handleRegenerateBackupCodes}>
                        Regenerate Backup Codes
                    </Button>
                </div>
            )}
        </AccountLayout>
    );
}
