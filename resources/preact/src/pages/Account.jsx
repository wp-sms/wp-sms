import { useEffect } from 'preact/hooks';
import { User, Shield, KeyRound } from 'lucide-react';
import { currentUser } from '../signals/auth';
import { loadCurrentUser, userLoading } from '../signals/user';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { authUrl } from '../utils/urls';
import { AccountLayout } from '../layouts/AccountLayout';
import { Spinner } from '../components/ui/Spinner';

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

    const navItems = [
        {
            href: authUrl('/profile'),
            icon: User,
            title: 'Profile',
            description: 'Update your name, email, and phone number',
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
            <div className="space-y-3">
                {navItems.map((item) => (
                    <a
                        key={item.href}
                        href={item.href}
                        className="flex items-center gap-4 rounded-lg border bg-card p-4 no-underline text-foreground transition-colors hover:border-primary hover:shadow-sm"
                    >
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted">
                            <item.icon className="size-5 text-muted-foreground" />
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
