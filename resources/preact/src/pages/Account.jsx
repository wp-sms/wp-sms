import { useEffect } from 'preact/hooks';
import { Mail, Phone, Shield, KeyRound } from 'lucide-react';
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
        <a
            href={href}
            className={`flex items-start gap-3 rounded-lg border bg-card p-4 no-underline text-foreground transition-colors hover:border-primary hover:shadow-sm ${
                accent ? 'border-l-2 border-l-amber-400' : ''
            }`}
        >
            <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted">
                <Icon className="size-4 text-muted-foreground" />
            </span>
            <div className="min-w-0 flex-1 space-y-1">
                <div className="text-xs font-medium text-muted-foreground">{label}</div>
                <div className="truncate text-sm font-semibold">{value}</div>
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
                <div className="flex flex-col items-center gap-3 py-8">
                    <Spinner className="size-8" />
                    <p className="text-sm text-muted-foreground">Loading your account…</p>
                </div>
            </AccountLayout>
        );
    }

    const user = currentUser.value;
    if (!user) return null;

    const emailBadge = user.email_verified ? 'verified' : 'unverified';
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
            <div className="mb-6 flex items-center gap-4">
                <UserAvatar user={user} size="lg" />
                <div className="min-w-0">
                    <div className="text-xl font-semibold">{user.display_name || user.username}</div>
                    <div className="text-sm text-muted-foreground truncate">{user.email}</div>
                    {user.username && user.username !== user.display_name && (
                        <div className="text-xs text-muted-foreground">@{user.username}</div>
                    )}
                </div>
            </div>

            {/* Status Cards Grid */}
            <div className="grid gap-3 sm:grid-cols-2">
                <StatusCard
                    href={authUrl('/profile')}
                    icon={Mail}
                    label="Email"
                    value={maskEmail(user.email)}
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
                    value="Change password"
                />
            </div>
        </AccountLayout>
    );
}
