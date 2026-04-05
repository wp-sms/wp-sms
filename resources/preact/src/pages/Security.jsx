import { useState, useEffect } from 'preact/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { api } from '../api/client';
import { socialProviders } from '../signals/config';
import { currentUser } from '../signals/auth';
import { loadCurrentUser, refreshUser, enrolledFactors } from '../signals/user';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { extractError, redirectTo } from '../utils/auth';
import { getBaseUrl } from '../utils/urls';
import { enrollChannel, loadMfaMethods } from '../utils/mfa-enrollment';
import { AccountLayout } from '../layouts/AccountLayout';
import { Alert } from '../components/ui/Alert';
import { Button } from '../components/ui/Button';
import { SecuritySkeleton } from '../components/ui/Skeleton';
import { MfaFactorCard } from '../components/MfaFactorCard';
import { BackupCodesDisplay } from '../components/BackupCodesDisplay';

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

        // Redirect enrollment-gated users to the wizard
        if (isEnrollmentGated) {
            redirectTo(getBaseUrl() + '/security/enroll?mode=forced');
            return;
        }

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
            const [, methods, accountsRes] = await Promise.all([
                currentUser.value ? Promise.resolve() : loadCurrentUser(),
                loadMfaMethods(),
                api.get('/auth/social/accounts').catch(() => ({ accounts: [] })),
            ]);

            setAvailableMethods(methods);
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
                setSuccess(res.message || __('Account unlinked.', 'wp-sms'));
            } else {
                setError(res.message || __('Failed to unlink account.', 'wp-sms'));
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
            const res = await enrollChannel(channelId, data);

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
            <AccountLayout title={__('Security', 'wp-sms')} currentPath="/security">
                <SecuritySkeleton />
            </AccountLayout>
        );
    }

    const user = currentUser.value;

    return (
        <AccountLayout title={__('Security', 'wp-sms')} subtitle={__('Manage your multi-factor authentication methods', 'wp-sms')} currentPath="/security" hideNav={isEnrollmentGated}>
            {isEnrollmentGated && (
                <Alert variant="info" className="wsms-auth-mb-4">
                    {__('MFA enrollment is required. Please enable at least one authentication method below to continue.', 'wp-sms')}
                </Alert>
            )}
            {graceNotice && (
                <Alert variant="info" onDismiss={() => setGraceNotice(null)} className="wsms-auth-mb-4">
                    {sprintf(__('Two-factor authentication will be required in %d days. Set it up now on the Security page.', 'wp-sms'), graceNotice.grace_period_remaining_days)}
                </Alert>
            )}
            <Alert variant="destructive" message={error} onDismiss={() => setError('')} className="wsms-auth-mb-4" />
            <Alert variant="success" message={success} className="wsms-auth-mb-4" />

            {backupCodes && (
                <BackupCodesDisplay
                    codes={backupCodes}
                    onDismiss={() => setBackupCodes(null)}
                />
            )}

            {availableMethods.length > 0 && (
                <div className="wsms-auth-section-group">
                    <h3 className="wsms-auth-section-heading">{__('Authentication Methods', 'wp-sms')}</h3>
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">
                        {__('Enable additional factors for your account', 'wp-sms')}
                    </p>
                    <div className="wsms-auth-stack-3">
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
                </div>
            )}

            {socialProviders.value.length > 0 && (
                <div className="wsms-auth-section-group wsms-auth-mt-4">
                    <h3 className="wsms-auth-section-heading">{__('Linked Accounts', 'wp-sms')}</h3>
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">
                        {__('Connect social accounts for easier sign-in.', 'wp-sms')}
                    </p>
                    <div className="wsms-auth-stack-2">
                        {socialProviders.value.map((provider) => {
                            const linked = linkedAccounts.find((a) => a.provider === provider.id);
                            return (
                                <div key={provider.id} className="wsms-auth-linked-account">
                                    <div className="wsms-auth-linked-account__info">
                                        <span dangerouslySetInnerHTML={{ __html: provider.icon }} />
                                        <div>
                                            <div className="wsms-auth-text-sm wsms-auth-font-medium">{provider.name}</div>
                                            {linked && (
                                                <div className="wsms-auth-text-xs wsms-auth-text-muted">{linked.email}</div>
                                            )}
                                        </div>
                                    </div>
                                    {linked ? (
                                        <Button variant="outline" size="sm" onClick={() => handleUnlinkProvider(provider.id)}>
                                            {__('Unlink', 'wp-sms')}
                                        </Button>
                                    ) : (
                                        <Button variant="outline" size="sm" onClick={() => handleLinkProvider(provider.id)}>
                                            {__('Link', 'wp-sms')}
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {isEnrolled('backup_codes') && availableMethods.length > 0 && (
                <div className="wsms-auth-section-group wsms-auth-mt-4">
                    <h3 className="wsms-auth-section-heading">{__('Backup Codes', 'wp-sms')}</h3>
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">
                        {(() => {
                            const codes = getFactorInfo('backup_codes')?.remaining_codes;
                            return codes != null ? sprintf(__('%d codes remaining', 'wp-sms'), codes) : __('Backup codes are enabled', 'wp-sms');
                        })()}
                    </p>
                    <Button variant="outline" onClick={handleRegenerateBackupCodes}>
                        {__('Regenerate Backup Codes', 'wp-sms')}
                    </Button>
                </div>
            )}
        </AccountLayout>
    );
}
