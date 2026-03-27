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

    const headingClass = textLight ? 'wsms-auth-layout-split__heading--light' : 'wsms-auth-layout-split__heading--dark';
    const subtitleClass = textLight ? 'wsms-auth-layout-split__subtitle--light' : 'wsms-auth-layout-split__subtitle--dark';

    const brandPanel = (
        <div className="wsms-auth-layout-split__panel" style={panelStyle}>
            {panelImage && <div className="wsms-auth-layout-split__panel-overlay" />}
            <div className="wsms-auth-layout-split__panel-content">
                <h1 className={`wsms-auth-layout-split__heading ${headingClass}`}>{heading}</h1>
                <p className={`wsms-auth-layout-split__subtitle ${subtitleClass}`}>{sub}</p>
            </div>
        </div>
    );

    const redirecting = isRedirecting.value;

    const formPanel = (
        <div className="wsms-auth-layout-split__form">
            <div className="wsms-auth-layout-split__form-inner">
                <div className="wsms-auth-layout-split__mobile-logo">
                    <BrandLogo />
                </div>

                <Card className="wsms-auth-full wsms-auth-fade-in">
                    <CardHeader className="wsms-auth-center">
                        <CardTitle className="wsms-auth-text-xl">{title}</CardTitle>
                        {subtitle && <CardDescription>{subtitle}</CardDescription>}
                    </CardHeader>
                    <CardContent>
                        {redirecting ? <RedirectingOverlay /> : children}
                    </CardContent>
                    {!redirecting && footer && (
                        <>
                            <Separator />
                            <CardFooter className="wsms-auth-card__footer--center">
                                <div className="wsms-auth-text-sm wsms-auth-text-muted">{footer}</div>
                            </CardFooter>
                        </>
                    )}
                </Card>

                <SecuredByFooter />
            </div>
        </div>
    );

    return (
        <div className="wsms-auth-layout-split">
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
