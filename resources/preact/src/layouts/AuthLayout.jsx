import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/Card';
import { Separator } from '@/components/ui/Separator';

export function AuthLayout({ title, subtitle, children, footer }) {
    return (
        <div className="min-h-screen flex items-center justify-center bg-muted p-4 font-sans text-foreground antialiased">
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
        </div>
    );
}
