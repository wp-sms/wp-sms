import { useEffect } from 'preact/hooks';
import { Mail, Phone, Shield, KeyRound } from 'lucide-react';
import { cn } from '../utils/cn';
import { currentUser } from '../signals/auth';
import { loadCurrentUser, userLoading } from '../signals/user';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { authUrl } from '../utils/urls';
import { maskEmail, maskPhone } from '../utils/format';
import { AccountLayout } from '../layouts/AccountLayout';
import { Spinner } from '../components/ui/Spinner';
import { StatusBadge } from '../components/ui/StatusBadge';
import { UserAvatar } from '../components/ui/UserAvatar';

function StatusCard({ href, icon: Icon, label, value, badge }) {
    const accent = badge?.props?.variant === 'unverified';
    return (
        <a href={href} className={cn('wsms-auth-status-card', accent && 'wsms-auth-status-card--accent')}>
            <span className="wsms-auth-status-card__icon">
                <Icon />
            </span>
            <div className="wsms-auth-status-card__body">
                <div className="wsms-auth-status-card__label">{label}</div>
                <div className="wsms-auth-status-card__value">{value}</div>
                {badge && <div>{badge}</div>}
            </div>
        </a>
    );
}

export function Account() {
    const authed = useAuthGuard();

    useEffect(() => {
        if (authed && !currentUser.value) loadCurrentUser();
    }, [authed]);

    if (!authed) return null;

    if (userLoading.value && !currentUser.value) {
        return (
            <AccountLayout title="Account" currentPath="/">
                <div className="wsms-auth-loading-center">
                    <Spinner className="wsms-auth-spinner--lg" />
                    <p className="wsms-auth-text-sm wsms-auth-text-muted">Loading your account…</p>
                </div>
            </AccountLayout>
        );
    }

    const user = currentUser.value;
    if (!user) return null;

    const emailBadge = user.has_placeholder_email ? 'not-set' : user.email_verified ? 'verified' : 'unverified';
    const phoneBadge = user.phone
        ? user.phone_verified
            ? 'verified'
            : 'unverified'
        : 'not-set';
    const mfaBadge = user.mfa_enabled ? 'verified' : 'unverified';
    const mfaLabel = user.mfa_enabled
        ? `MFA enabled (${user.enrolled_factors.length} factor${user.enrolled_factors.length !== 1 ? 's' : ''})`
        : 'Not set up';

    return (
        <AccountLayout title="Account" currentPath="/">
            {/* User Identity Header */}
            <div className="wsms-auth-identity-header">
                <UserAvatar user={user} size="lg" />
                <div className="wsms-auth-identity-header__info">
                    <div className="wsms-auth-identity-header__name">{user.display_name || user.username}</div>
                    <div className="wsms-auth-identity-header__email">{user.email}</div>
                    {user.username && user.username !== user.display_name && (
                        <div className="wsms-auth-identity-header__username">@{user.username}</div>
                    )}
                </div>
            </div>

            {/* Status Cards Grid */}
            <div className="wsms-auth-status-grid">
                <StatusCard
                    href={authUrl('/profile')}
                    icon={Mail}
                    label="Email"
                    value={user.has_placeholder_email ? 'No email added' : maskEmail(user.email)}
                    badge={<StatusBadge variant={emailBadge} />}
                />
                <StatusCard
                    href={authUrl('/profile')}
                    icon={Phone}
                    label="Phone"
                    value={user.phone ? maskPhone(user.phone) : 'No phone added'}
                    badge={<StatusBadge variant={phoneBadge} />}
                />
                <StatusCard
                    href={authUrl('/security')}
                    icon={Shield}
                    label="Security"
                    value={mfaLabel}
                    badge={<StatusBadge variant={mfaBadge} />}
                />
                <StatusCard
                    href={authUrl('/change-password')}
                    icon={KeyRound}
                    label="Password"
                    value={user.has_usable_password ? 'Change password' : 'Set password'}
                />
            </div>
        </AccountLayout>
    );
}
