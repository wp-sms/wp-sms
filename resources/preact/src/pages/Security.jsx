import { useState, useEffect } from 'preact/hooks';
import { api } from '../api/client';
import { currentUser } from '../signals/auth';
import { loadCurrentUser, refreshUser, enrolledFactors } from '../signals/user';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { extractError } from '../utils/auth';
import { authUrl } from '../utils/urls';
import { Alert } from '../components/Alert';
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
            <div class="wsms-page">
                <div class="wsms-loader">
                    <div class="wsms-spinner" />
                    <p class="wsms-subtitle">Loading security settings\u2026</p>
                </div>
            </div>
        );
    }

    return (
        <div class="wsms-page">
            <h1 class="wsms-title">Security</h1>
            <p class="wsms-subtitle">Manage your multi-factor authentication methods</p>

            <Alert type="error" message={error} onDismiss={() => setError('')} />
            <Alert type="success" message={success} />

            {backupCodes && (
                <BackupCodesDisplay
                    codes={backupCodes}
                    onDismiss={() => setBackupCodes(null)}
                />
            )}

            <div class="wsms-factor-list">
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
                <div class="wsms-section">
                    <h2 class="wsms-section-title">Backup Codes</h2>
                    <p class="wsms-text-secondary">
                        {getFactorInfo('backup_codes')?.remaining_codes != null
                            ? `${getFactorInfo('backup_codes').remaining_codes} codes remaining`
                            : 'Backup codes are enabled'}
                    </p>
                    <button
                        type="button"
                        class="wsms-btn wsms-btn--secondary"
                        onClick={handleRegenerateBackupCodes}
                    >
                        Regenerate Backup Codes
                    </button>
                </div>
            )}

            <div class="wsms-links">
                <a href={authUrl('/')} class="wsms-link">Back to account</a>
            </div>
        </div>
    );
}
