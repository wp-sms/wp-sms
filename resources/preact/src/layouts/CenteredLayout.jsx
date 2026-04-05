import { __ } from '@wordpress/i18n';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/Card';
import { Separator } from '@/components/ui/Separator';
import { RedirectingOverlay } from '@/components/ui/RedirectingOverlay';
import { Spinner } from '@/components/ui/Spinner';
import { BrandLogo } from '@/components/BrandLogo';
import { SecuredByFooter } from '@/components/SecuredByFooter';
import { brandingConfig } from '@/signals/branding';
import { isRedirecting } from '@/signals/auth';
import { renderMode } from '@/signals/config';

const ALIGN_MAP = {
    left: 'wsms-auth-layout-centered__logo-row--start',
    center: 'wsms-auth-layout-centered__logo-row--center',
    right: 'wsms-auth-layout-centered__logo-row--end',
};

export function CenteredLayout({ title, subtitle, children, footer, bare }) {
    const logoPosition = brandingConfig.value?.logo_position ?? 'center';
    const alignClass = ALIGN_MAP[logoPosition] ?? 'wsms-auth-layout-centered__logo-row--center';
    const isCompact = renderMode.value === 'popup' || renderMode.value === 'embed';

    const redirecting = isRedirecting.value;

    if (isCompact) {
        return (
            <div className="wsms-auth-layout-centered--compact">
                {logoPosition !== 'hidden' && (
                    <div className={`wsms-auth-layout-centered__logo-row ${alignClass}`}>
                        <BrandLogo />
                    </div>
                )}
                <div className="wsms-auth-center wsms-auth-mb-4">
                    <h2 className="wsms-auth-text-xl wsms-auth-font-semibold" style={{ letterSpacing: '-0.025em' }}>{title}</h2>
                    {subtitle && <p className="wsms-auth-text-sm wsms-auth-text-muted wsms-auth-mt-1">{subtitle}</p>}
                </div>
                {redirecting ? <RedirectingOverlay /> : children}
                {!redirecting && footer && (
                    <>
                        <Separator className="wsms-auth-my-4" />
                        <div className="wsms-auth-center wsms-auth-text-sm wsms-auth-text-muted">{footer}</div>
                    </>
                )}
                <SecuredByFooter className="wsms-auth-mt-4" />
            </div>
        );
    }

    if (bare) {
        return (
            <div className="wsms-auth-layout-centered">
                {logoPosition !== 'hidden' && (
                    <div className={`wsms-auth-layout-centered__logo-row ${alignClass}`}>
                        <BrandLogo />
                    </div>
                )}
                <div className="wsms-auth-layout-bare">
                    {children}
                </div>
                <SecuredByFooter className="wsms-auth-mt-6" />
            </div>
        );
    }

    return (
        <div className="wsms-auth-layout-centered">
            {logoPosition !== 'hidden' && (
                <div className={`wsms-auth-layout-centered__logo-row ${alignClass}`}>
                    <BrandLogo />
                </div>
            )}

            <Card className="wsms-auth-full wsms-auth-fade-in" style={{ maxWidth: '28rem' }}>
                <CardHeader className="wsms-auth-center">
                    <CardTitle className="wsms-auth-text-xl">{title}</CardTitle>
                    {subtitle && <CardDescription>{subtitle}</CardDescription>}
                </CardHeader>
                <CardContent>
                    {redirecting ? (
                        <div className="wsms-auth-loading-center wsms-auth-fade-in">
                            <Spinner className="wsms-auth-spinner--lg" />
                            <p className="wsms-auth-text-sm wsms-auth-text-muted">{__('Redirecting\u2026', 'wp-sms')}</p>
                        </div>
                    ) : children}
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

            <SecuredByFooter className="wsms-auth-mt-6" />
        </div>
    );
}
