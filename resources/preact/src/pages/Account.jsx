import { useEffect } from 'preact/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { Mail, Phone, Shield, KeyRound, ChevronRight } from 'lucide-react';
import { cn } from '../utils/cn';
import { currentUser } from '../signals/auth';
import { loadCurrentUser, userLoading } from '../signals/user';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { authUrl } from '../utils/urls';
import { maskEmail, maskPhone } from '../utils/format';
import { AccountLayout } from '../layouts/AccountLayout';
import { AccountSkeleton } from '../components/ui/Skeleton';
import { StatusBadge } from '../components/ui/StatusBadge';
import { UserAvatar } from '../components/ui/UserAvatar';

function StatusCard({ href, icon: Icon, iconVariant, label, value, badge }) {
    const accent = badge?.props?.variant === 'unverified';
    return (
        <a href={href} className={cn('wsms-auth-status-card', accent && 'wsms-auth-status-card--accent')}>
            <span className={cn('wsms-auth-status-card__icon', iconVariant && `wsms-auth-status-card__icon--${iconVariant}`)}>
                <Icon />
            </span>
            <div className="wsms-auth-status-card__body">
                <div className="wsms-auth-status-card__label">{label}</div>
                <div className="wsms-auth-status-card__value">{value}</div>
                {badge && <div>{badge}</div>}
            </div>
            <ChevronRight className="wsms-auth-status-card__chevron" />
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
            <AccountLayout title={__('Account', 'wp-sms')} currentPath="/">
                <AccountSkeleton />
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
        ? sprintf(__('MFA enabled (%d factors)', 'wp-sms'), user.enrolled_factors.length)
        : __('Not set up', 'wp-sms');

    return (
        <AccountLayout title={__('Account', 'wp-sms')} currentPath="/">
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
                    iconVariant="info"
                    label={__('Email', 'wp-sms')}
                    value={user.has_placeholder_email ? __('No email added', 'wp-sms') : maskEmail(user.email)}
                    badge={<StatusBadge variant={emailBadge} />}
                />
                <StatusCard
                    href={authUrl('/profile')}
                    icon={Phone}
                    iconVariant="success"
                    label={__('Phone', 'wp-sms')}
                    value={user.phone ? maskPhone(user.phone) : __('No phone added', 'wp-sms')}
                    badge={<StatusBadge variant={phoneBadge} />}
                />
                <StatusCard
                    href={authUrl('/security')}
                    icon={Shield}
                    iconVariant="primary"
                    label={__('Security', 'wp-sms')}
                    value={mfaLabel}
                    badge={<StatusBadge variant={mfaBadge} />}
                />
                <StatusCard
                    href={authUrl('/change-password')}
                    icon={KeyRound}
                    label={__('Password', 'wp-sms')}
                    value={user.has_usable_password ? __('Change password', 'wp-sms') : __('Set password', 'wp-sms')}
                />
            </div>
        </AccountLayout>
    );
}
