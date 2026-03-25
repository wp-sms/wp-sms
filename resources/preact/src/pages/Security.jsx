import { useState, useEffect } from 'preact/hooks';
import { api } from '../api/client';
import { socialProviders, enabledChannels, authConfig } from '../signals/config';
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

function SecurityPosture({ user }) {
    const channels = enabledChannels.value;
    const mfaEnabled = authConfig.value?.mfa_enabled;

    const steps = [];
    if (channels.includes('email')) {
        steps.push({ label: 'Email verified', done: !user.has_placeholder_email && !!user.email_verified });
    }
    if (channels.includes('phone')) {
        steps.push({ label: 'Phone verified', done: !!(user.phone && user.phone_verified) });
    }
    if (mfaEnabled) {
        steps.push({ label: 'MFA enabled', done: !!user.mfa_enabled });
    }
    if (steps.length === 0) return null;

    const completed = steps.filter((s) => s.done).length;
    const total = steps.length;
    const allDone = completed === total;
    const noneDone = completed === 0;

    const barColor = allDone ? 'bg-green-500' : noneDone ? 'bg-red-400' : 'bg-amber-400';
    const textColor = allDone ? 'text-green-700' : noneDone ? 'text-red-600' : 'text-amber-700';

    return (
        <div className="mb-6 rounded-lg border bg-card p-4">
            <div className="mb-3 flex items-center justify-between">
                <span className={`text-sm font-semibold ${textColor}`}>
                    {completed} of {total} security steps completed
                </span>
            </div>

            {/* Segment indicators */}
            <div className="flex gap-1.5">
                {steps.map((step) => (
                    <div key={step.label} className="flex-1 space-y-1">
                        <div
                            className={`h-1.5 rounded-full ${step.done ? barColor : 'bg-muted'}`}
                        />
                        <div
                            className={`text-[10px] leading-tight ${step.done ? 'font-medium text-foreground' : 'text-muted-foreground'}`}
                        >
                            {step.label}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export function Security() {
    const authed = useAuthGuard();
    const [availableMethods, setAvailableMethods] = useState([]);
    const [linkedAccounts, setLinkedAccounts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [backupCodes, setBackupCodes] = useState(null);
    const [graceNotice, setGraceNotice] = useState(null);

    const isEnrollmentGated = !!window.wsmsAuth?.enrollmentGated;

    useEffect(() => {
        if (!authed) return;
        init();
        const raw = sessionStorage.getItem('wsms_grace_period');
        if (raw) {
            try {
                const parsed = JSON.parse(raw);
                if (parsed?.grace_period_remaining_days) {
                    setGraceNotice(parsed);
                }
            } catch { /* ignore corrupt data */ }
            sessionStorage.removeItem('wsms_grace_period');
        }
    }, [authed]);

    async function init() {
        setLoading(true);
        try {
            const [, methodsRes, accountsRes, configRes] = await Promise.all([
                currentUser.value ? Promise.resolve() : loadCurrentUser(),
                api.get('/auth/methods'),
                api.get('/auth/social/accounts').catch(() => ({ accounts: [] })),
                isEnrollmentGated ? api.get('/auth/config').catch(() => null) : Promise.resolve(null),
            ]);

            if (isEnrollmentGated && configRes && !configRes.enrollment_required) {
                window.location.reload();
                return;
            }

            setAvailableMethods(methodsRes.methods.filter((m) => m.supports_mfa));
            setLinkedAccounts(accountsRes.accounts || []);
        } catch (err) {
            setError(extractError(err).message);
        } finally {
            setLoading(false);
        }
    }

    async function handleLinkProvider(providerId) {
        setError('');
        try {
            const res = await api.post(`/auth/social/link/${providerId}`);
            if (res.authorize_url) {
                window.location.href = res.authorize_url;
            }
        } catch (err) {
            setError(extractError(err).message);
        }
    }

    async function handleUnlinkProvider(providerId) {
        setError('');
        setSuccess('');
        try {
            const res = await api.del(`/auth/social/unlink/${providerId}`);
            if (res.success) {
                setLinkedAccounts((prev) => prev.filter((a) => a.provider !== providerId));
                setSuccess(res.message || 'Account unlinked.');
            } else {
                setError(res.message || 'Failed to unlink account.');
            }
        } catch (err) {
            setError(extractError(err).message);
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
            setError(extractError(err).message);
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
            setError(extractError(err).message);
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
            setError(extractError(err).message);
        }
    }

    if (!authed) return null;

    if (loading) {
        return (
            <AccountLayout title="Security" currentPath="/security">
                <div className="flex flex-col items-center gap-3 py-8">
                    <Spinner className="size-8" />
                    <p className="text-sm text-muted-foreground">Loading security settings…</p>
                </div>
            </AccountLayout>
        );
    }

    const user = currentUser.value;

    return (
        <AccountLayout title="Security" subtitle="Manage your multi-factor authentication methods" currentPath="/security" hideNav={isEnrollmentGated}>
            {isEnrollmentGated && (
                <Alert variant="info" className="mb-4">
                    MFA enrollment is required. Please enable at least one authentication method below to continue.
                </Alert>
            )}
            {graceNotice && (
                <Alert variant="info" onDismiss={() => setGraceNotice(null)} className="mb-4">
                    Two-factor authentication will be required in {graceNotice.grace_period_remaining_days} day{graceNotice.grace_period_remaining_days !== 1 ? 's' : ''}.
                    Set it up now on the Security page.
                </Alert>
            )}
            <Alert variant="destructive" message={error} onDismiss={() => setError('')} className="mb-4" />
            <Alert variant="success" message={success} className="mb-4" />

            {user && <SecurityPosture user={user} />}

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
                        onBackupCodes={setBackupCodes}
                    />
                ))}
            </div>

            {socialProviders.value.length > 0 && (
                <div className="mt-6 space-y-3">
                    <Separator />
                    <h3 className="text-base font-semibold">Linked Accounts</h3>
                    <p className="text-sm text-muted-foreground">
                        Connect social accounts for easier sign-in.
                    </p>
                    <div className="space-y-2">
                        {socialProviders.value.map((provider) => {
                            const linked = linkedAccounts.find((a) => a.provider === provider.id);
                            return (
                                <div key={provider.id} className="flex items-center justify-between rounded-lg border p-3">
                                    <div className="flex items-center gap-3">
                                        <span dangerouslySetInnerHTML={{ __html: provider.icon }} />
                                        <div>
                                            <div className="text-sm font-medium">{provider.name}</div>
                                            {linked && (
                                                <div className="text-xs text-muted-foreground">{linked.email}</div>
                                            )}
                                        </div>
                                    </div>
                                    {linked ? (
                                        <Button variant="outline" size="sm" onClick={() => handleUnlinkProvider(provider.id)}>
                                            Unlink
                                        </Button>
                                    ) : (
                                        <Button variant="outline" size="sm" onClick={() => handleLinkProvider(provider.id)}>
                                            Link
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {isEnrolled('backup_codes') && availableMethods.length > 0 && (
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
