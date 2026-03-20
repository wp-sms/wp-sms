import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/Card';
import { Separator } from '@/components/ui/Separator';
import { BrandLogo } from '@/components/BrandLogo';
import { SecuredByFooter } from '@/components/SecuredByFooter';
import { brandingConfig } from '@/signals/branding';

const ALIGN_MAP = {
    left: 'justify-start',
    center: 'justify-center',
    right: 'justify-end',
};

export function CenteredLayout({ title, subtitle, children, footer }) {
    const logoPosition = brandingConfig.value?.logo_position ?? 'center';
    const alignClass = ALIGN_MAP[logoPosition] ?? 'justify-center';

    return (
        <div className="min-h-screen flex flex-col items-center justify-center bg-muted p-4 font-sans text-foreground antialiased">
            {logoPosition !== 'hidden' && (
                <div className={`mb-6 flex w-full max-w-md ${alignClass}`}>
                    <BrandLogo />
                </div>
            )}

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
