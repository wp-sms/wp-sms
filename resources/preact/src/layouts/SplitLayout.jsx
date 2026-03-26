import { useMemo } from 'preact/hooks';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/Card';
import { Separator } from '@/components/ui/Separator';
import { RedirectingOverlay } from '@/components/ui/RedirectingOverlay';
import { BrandLogo } from '@/components/BrandLogo';
import { SecuredByFooter } from '@/components/SecuredByFooter';
import { brandingConfig } from '@/signals/branding';
import { isRedirecting } from '@/signals/auth';
import { isLightColor } from '@/utils/color';

export function SplitLayout({ title, subtitle, children, footer }) {
    const config = brandingConfig.value || {};
    const position = config.split_panel_position || 'left';
    const panelBg = config.split_panel_bg_color || '#1e293b';
    const panelImage = config.split_panel_bg_image_url;
    const heading = config.split_welcome_heading || 'Welcome back';
    const sub = config.split_subtitle || 'Sign in to continue';
    const textLight = useMemo(() => !isLightColor(panelBg), [panelBg]);

    const panelStyle = {
        backgroundColor: panelBg,
        ...(panelImage && {
            backgroundImage: `url(${panelImage})`,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
        }),
    };

    const textColor = textLight ? 'text-white' : 'text-gray-900';
    const subtitleColor = textLight ? 'text-white/70' : 'text-gray-600';

    const brandPanel = (
        <div
            className="hidden lg:flex lg:w-1/2 flex-col items-center justify-center p-12 relative"
            style={panelStyle}
        >
            {panelImage && (
                <div className="absolute inset-0 bg-black/30" />
            )}
            <div className="relative z-10 max-w-md text-center space-y-4">
                <h1 className={`text-3xl font-bold tracking-tight ${textColor}`}>
                    {heading}
                </h1>
                <p className={`text-lg ${subtitleColor}`}>
                    {sub}
                </p>
            </div>
        </div>
    );

    const redirecting = isRedirecting.value;

    const formPanel = (
        <div className="flex w-full lg:w-1/2 flex-col items-center justify-center bg-background p-4 md:p-8">
            <div className="w-full max-w-md space-y-6">
                <div className="lg:hidden mb-6">
                    <BrandLogo />
                </div>

                <Card className="w-full animate-fade-in">
                    <CardHeader className="text-center">
                        <CardTitle className="text-xl">{title}</CardTitle>
                        {subtitle && <CardDescription>{subtitle}</CardDescription>}
                    </CardHeader>
                    <CardContent>
                        {redirecting ? <RedirectingOverlay /> : children}
                    </CardContent>
                    {!redirecting && footer && (
                        <>
                            <Separator />
                            <CardFooter className="justify-center">
                                <div className="text-sm text-muted-foreground">{footer}</div>
                            </CardFooter>
                        </>
                    )}
                </Card>

                <SecuredByFooter />
            </div>
        </div>
    );

    return (
        <div className="min-h-screen flex font-sans text-foreground antialiased">
            {position === 'left' ? (
                <>
                    {brandPanel}
                    {formPanel}
                </>
            ) : (
                <>
                    {formPanel}
                    {brandPanel}
                </>
            )}
        </div>
    );
}
