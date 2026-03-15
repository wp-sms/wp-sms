import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/Card';
import { Separator } from '@/components/ui/Separator';
import { Logo } from '@/components/Logo';
import { SecuredByFooter } from '@/components/SecuredByFooter';

export function AuthLayout({ title, subtitle, children, footer }) {
    return (
        <div className="min-h-screen flex flex-col items-center justify-center bg-muted p-4 font-sans text-foreground antialiased">
            <div className="mb-6 flex items-center gap-2">
                <div className="flex size-9 items-center justify-center rounded-lg bg-primary">
                    <Logo className="size-5 text-primary-foreground" />
                </div>
                <span className="text-lg font-semibold tracking-tight">WSMS</span>
            </div>

            <Card className="w-full max-w-md animate-fade-in">
                <CardHeader className="text-center">
                    <CardTitle className="text-xl">{title}</CardTitle>
                    {subtitle && <CardDescription>{subtitle}</CardDescription>}
                </CardHeader>
                <CardContent>{children}</CardContent>
                {footer && (
                    <>
                        <Separator />
                        <CardFooter className="justify-center">
                            <div className="text-sm text-muted-foreground">{footer}</div>
                        </CardFooter>
                    </>
                )}
            </Card>

            <SecuredByFooter className="mt-6" />
        </div>
    );
}
