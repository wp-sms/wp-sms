import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { UserAvatar } from '@/components/ui/UserAvatar';
import { BrandLogo } from '@/components/BrandLogo';
import { SecuredByFooter } from '@/components/SecuredByFooter';
import { cn } from '@/utils/cn';
import { authUrl } from '@/utils/urls';
import { logout } from '@/utils/auth';
import { currentUser } from '@/signals/auth';

const NAV_ITEMS = [
    { path: '/', label: 'Dashboard' },
    { path: '/profile', label: 'Profile' },
    { path: '/security', label: 'Security' },
    { path: '/change-password', label: 'Password' },
];

function NavLinks({ currentPath, className, linkClassName }) {
    return (
        <nav className={cn('flex gap-1', className)}>
            {NAV_ITEMS.map((item) => (
                <a
                    key={item.path}
                    href={authUrl(item.path)}
                    className={cn(
                        'px-3 py-1.5 rounded-md text-sm font-medium transition-colors no-underline',
                        currentPath === item.path
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:text-foreground hover:bg-accent',
                        linkClassName,
                    )}
                >
                    {item.label}
                </a>
            ))}
        </nav>
    );
}

export function AccountLayout({ title, subtitle, currentPath, hideNav, children }) {
    const user = currentUser.value;

    return (
        <div className="min-h-screen flex flex-col bg-muted p-4 md:p-8 font-sans text-foreground antialiased">
            <div className="mx-auto w-full max-w-3xl flex-1 space-y-6">
                <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <BrandLogo size="sm" />
                            {!hideNav && (
                                <div className="hidden md:flex md:items-center md:gap-3">
                                    <div className="h-5 w-px bg-border" />
                                    <NavLinks currentPath={currentPath} />
                                </div>
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
                    {!hideNav && (
                        <NavLinks
                            currentPath={currentPath}
                            className="overflow-x-auto md:hidden"
                            linkClassName="flex-1 text-center whitespace-nowrap"
                        />
                    )}
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
