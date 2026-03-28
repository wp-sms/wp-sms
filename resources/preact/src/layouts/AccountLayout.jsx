import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { UserAvatar } from '@/components/ui/UserAvatar';
import { BrandLogo } from '@/components/BrandLogo';
import { SecuredByFooter } from '@/components/SecuredByFooter';
import { LayoutDashboard, User, Shield, KeyRound } from 'lucide-react';
import { cn } from '@/utils/cn';
import { authUrl } from '@/utils/urls';
import { logout } from '@/utils/auth';
import { currentUser } from '@/signals/auth';

const NAV_ITEMS = [
    { path: '/', label: 'Dashboard', icon: LayoutDashboard },
    { path: '/profile', label: 'Profile', icon: User },
    { path: '/security', label: 'Security', icon: Shield },
    { path: '/change-password', label: 'Password', icon: KeyRound },
];

function NavLinks({ currentPath, className, linkClassName }) {
    return (
        <nav className={cn('wsms-auth-nav', className)}>
            {NAV_ITEMS.map((item) => (
                <a
                    key={item.path}
                    href={authUrl(item.path)}
                    className={cn(
                        'wsms-auth-nav-link',
                        currentPath === item.path && 'wsms-auth-nav-link--active',
                        linkClassName,
                    )}
                >
                    <item.icon className="wsms-auth-nav-link__icon" />
                    {item.label}
                </a>
            ))}
        </nav>
    );
}

export function AccountLayout({ title, subtitle, currentPath, hideNav, children }) {
    const user = currentUser.value;

    return (
        <div className="wsms-auth-layout-account">
            <div className="wsms-auth-layout-account__inner">
                <div className="wsms-auth-layout-account__topbar">
                    <div className="wsms-auth-layout-account__topbar-main">
                        <div className="wsms-auth-layout-account__brand-group">
                            <BrandLogo size="sm" />
                            {!hideNav && (
                                <div className="wsms-auth-layout-account__desktop-nav">
                                    <div className="wsms-auth-layout-account__nav-divider" />
                                    <NavLinks currentPath={currentPath} />
                                </div>
                            )}
                        </div>
                        <div className="wsms-auth-layout-account__user-section">
                            {user && (
                                <div className="wsms-auth-layout-account__user-info">
                                    <UserAvatar user={user} size="sm" />
                                    <span className="wsms-auth-layout-account__user-name">
                                        {user.display_name || user.username}
                                    </span>
                                </div>
                            )}
                            <Button
                                variant="ghost"
                                size="sm"
                                className="wsms-auth-layout-account__signout"
                                onClick={logout}
                            >
                                Sign Out
                            </Button>
                        </div>
                    </div>
                    {!hideNav && (
                        <NavLinks
                            currentPath={currentPath}
                            className="wsms-auth-layout-account__mobile-nav"
                        />
                    )}
                </div>

                <Card className="wsms-auth-fade-in">
                    <CardHeader>
                        <CardTitle className="wsms-auth-text-xl">{title}</CardTitle>
                        {subtitle && <CardDescription>{subtitle}</CardDescription>}
                    </CardHeader>
                    <CardContent>{children}</CardContent>
                </Card>

                <SecuredByFooter />
            </div>
        </div>
    );
}
