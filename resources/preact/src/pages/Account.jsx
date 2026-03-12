import { useEffect } from 'preact/hooks';
import { User, Shield, KeyRound, Mail, Phone } from 'lucide-react';
import { currentUser } from '../signals/auth';
import { loadCurrentUser, userLoading } from '../signals/user';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { authUrl } from '../utils/urls';
import { AccountLayout } from '../layouts/AccountLayout';
import { Spinner } from '../components/ui/Spinner';

function maskEmail(email) {
    if (!email) return '';
    const [local, domain] = email.split('@');
    if (!domain) return email;
    return local[0] + '***@' + domain;
}

function maskPhone(phone) {
    if (!phone) return '';
    const digits = phone.replace(/\D/g, '');
    if (digits.length < 4) return phone;
    return '+' + digits.slice(0, -4).replace(/\d/g, '*') + digits.slice(-4);
}

function VerificationWarning({ icon: Icon, label, maskedValue, actionLabel }) {
    return (
        <div className="flex items-center gap-3 rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
            <Icon className="size-4 shrink-0" />
            <span className="flex-1">
                {label} <span className="font-medium">{maskedValue}</span> is not verified
            </span>
            <a
                href={authUrl('/profile')}
                className="shrink-0 rounded-md bg-yellow-600 px-3 py-1 text-xs font-medium text-white no-underline hover:bg-yellow-700"
            >
                {actionLabel}
            </a>
        </div>
    );
}

export function Account() {
    const authed = useAuthGuard();

    useEffect(() => {
        if (authed) loadCurrentUser();
    }, [authed]);

    if (!authed) return null;

    if (userLoading.value && !currentUser.value) {
        return (
            <AccountLayout title="Account" currentPath="/">
                <div className="flex flex-col items-center gap-3 py-8">
                    <Spinner className="size-8" />
                    <p className="text-sm text-muted-foreground">Loading your account\u2026</p>
                </div>
            </AccountLayout>
        );
    }

    const user = currentUser.value;
    if (!user) return null;

    const hasUnverified = !user.email_verified || (user.phone && !user.phone_verified);

    const navItems = [
        {
            href: authUrl('/profile'),
            icon: User,
            title: 'Profile',
            description: 'Update your name, email, and phone number',
            badge: hasUnverified,
        },
        {
            href: authUrl('/security'),
            icon: Shield,
            title: 'Security',
            description: user.mfa_enabled
                ? `MFA enabled (${user.enrolled_factors.length} factor${user.enrolled_factors.length !== 1 ? 's' : ''})`
                : 'Set up multi-factor authentication',
        },
        {
            href: authUrl('/change-password'),
            icon: KeyRound,
            title: 'Change Password',
            description: 'Update your password',
        },
    ];

    return (
        <AccountLayout title={`Welcome, ${user.display_name || user.username}`} subtitle={user.email} currentPath="/">
            {hasUnverified && (
                <div className="mb-4 space-y-2">
                    {!user.email_verified && (
                        <VerificationWarning icon={Mail} label="Email" maskedValue={maskEmail(user.email)} actionLabel="Verify email" />
                    )}
                    {user.phone && !user.phone_verified && (
                        <VerificationWarning icon={Phone} label="Phone" maskedValue={maskPhone(user.phone)} actionLabel="Verify phone" />
                    )}
                </div>
            )}
            <div className="space-y-3">
                {navItems.map((item) => (
                    <a
                        key={item.href}
                        href={item.href}
                        className="flex items-center gap-4 rounded-lg border bg-card p-4 no-underline text-foreground transition-colors hover:border-primary hover:shadow-sm"
                    >
                        <span className="relative flex size-10 shrink-0 items-center justify-center rounded-md bg-muted">
                            <item.icon className="size-5 text-muted-foreground" />
                            {item.badge && (
                                <span className="absolute -top-1 -right-1 size-3 rounded-full bg-yellow-500 border-2 border-card" />
                            )}
                        </span>
                        <div>
                            <div className="text-sm font-semibold">{item.title}</div>
                            <div className="text-xs text-muted-foreground">{item.description}</div>
                        </div>
                    </a>
                ))}
            </div>
        </AccountLayout>
    );
}
