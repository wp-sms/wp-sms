import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { UserAvatar } from '@/components/ui/UserAvatar';
import { Logo } from '@/components/Logo';
import { SecuredByFooter } from '@/components/SecuredByFooter';
import { authUrl } from '@/utils/urls';
import { logout } from '@/utils/auth';
import { currentUser } from '@/signals/auth';

const NAV_ITEMS = [
    { path: '/', label: 'Dashboard' },
    { path: '/profile', label: 'Profile' },
    { path: '/security', label: 'Security' },
    { path: '/change-password', label: 'Password' },
];

export function AccountLayout({ title, subtitle, currentPath, hideNav, children }) {
    const user = currentUser.value;

    return (
        <div className="min-h-screen flex flex-col bg-muted p-4 md:p-8 font-sans text-foreground antialiased">
            <div className="mx-auto w-full max-w-3xl flex-1 space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-2">
                            <div className="flex size-7 items-center justify-center rounded-md bg-primary">
                                <Logo className="size-4 text-primary-foreground" />
                            </div>
                            <span className="hidden text-sm font-semibold tracking-tight sm:inline">WSMS</span>
                        </div>
                        {!hideNav && (
                            <>
                                <div className="h-5 w-px bg-border" />
                                <nav className="flex gap-1">
                                    {NAV_ITEMS.map((item) => {
                                        const active = currentPath === item.path;
                                        return (
                                            <a
                                                key={item.path}
                                                href={authUrl(item.path)}
                                                className={`px-3 py-1.5 rounded-md text-sm font-medium transition-colors no-underline ${
                                                    active
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'text-muted-foreground hover:text-foreground hover:bg-accent'
                                                }`}
                                            >
                                                {item.label}
                                            </a>
                                        );
                                    })}
                                </nav>
                            </>
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        {user && (
                            <div className="flex items-center gap-2">
                                <UserAvatar user={user} size="sm" />
                                <span className="hidden text-sm font-medium sm:inline">
                                    {user.display_name || user.username}
                                </span>
                            </div>
                        )}
                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-muted-foreground hover:bg-red-50 hover:text-red-600"
                            onClick={logout}
                        >
                            Sign Out
                        </Button>
                    </div>
                </div>

                <Card className="animate-fade-in">
                    <CardHeader>
                        <CardTitle className="text-xl">{title}</CardTitle>
                        {subtitle && <CardDescription>{subtitle}</CardDescription>}
                    </CardHeader>
                    <CardContent>{children}</CardContent>
                </Card>

                <SecuredByFooter />
            </div>
        </div>
    );
}
