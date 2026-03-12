import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { authUrl } from '@/utils/urls';
import { logout } from '@/utils/auth';

const NAV_ITEMS = [
    { path: '/', label: 'Dashboard' },
    { path: '/profile', label: 'Profile' },
    { path: '/security', label: 'Security' },
    { path: '/change-password', label: 'Password' },
];

export function AccountLayout({ title, subtitle, currentPath, children }) {
    return (
        <div className="min-h-screen bg-muted p-4 md:p-8 font-sans text-foreground antialiased">
            <div className="mx-auto max-w-3xl space-y-6">
                <div className="flex items-center justify-between">
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
                    <Button variant="ghost" size="sm" onClick={logout}>
                        Sign Out
                    </Button>
                </div>

                <Card className="animate-fade-in">
                    <CardHeader>
                        <CardTitle className="text-xl">{title}</CardTitle>
                        {subtitle && <CardDescription>{subtitle}</CardDescription>}
                    </CardHeader>
                    <CardContent>{children}</CardContent>
                </Card>
            </div>
        </div>
    );
}
