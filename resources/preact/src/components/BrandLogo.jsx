import { useState } from 'preact/hooks';
import { cn } from '@/utils/cn';
import { Logo } from './Logo';
import { brandingConfig } from '../signals/branding';

export function BrandLogo({ size = 'md', className }) {
    const config = brandingConfig.value;
    const logoUrl = config?.logo_url;
    const siteName = config?.site_name || 'WSMS';
    const logoSize = config?.logo_size ?? 40;
    const [imgError, setImgError] = useState(false);

    const nameClass = size === 'sm' ? 'wsms-auth-brand-logo__name--sm' : 'wsms-auth-brand-logo__name--lg';
    const hiddenClass = size === 'sm' && 'wsms-auth-brand-logo__name--hidden-mobile';
    const effectiveSize = size === 'sm' ? Math.min(logoSize, 28) : logoSize;

    if (logoUrl && !imgError) {
        return (
            <div className={cn('wsms-auth-brand-logo', className)}>
                <img
                    src={logoUrl}
                    alt={siteName}
                    style={{ maxHeight: `${effectiveSize}px`, maxWidth: '160px' }}
                    className="wsms-auth-brand-logo__img"
                    onError={() => setImgError(true)}
                />
                <span className={cn('wsms-auth-brand-logo__name', nameClass, hiddenClass)}>
                    {siteName}
                </span>
            </div>
        );
    }

    const iconPx = size === 'sm' ? 28 : Math.max(28, Math.min(effectiveSize, 48));
    const innerScale = iconPx <= 32 ? 0.5 : 0.55;

    return (
        <div className={cn('wsms-auth-brand-logo', className)}>
            <div
                className="wsms-auth-brand-logo__icon-box"
                style={{ width: `${iconPx}px`, height: `${iconPx}px` }}
            >
                <Logo
                    className="wsms-auth-brand-logo__icon-svg"
                    style={{ width: `${iconPx * innerScale}px`, height: `${iconPx * innerScale}px` }}
                />
            </div>
            <span className={cn('wsms-auth-brand-logo__name', nameClass, hiddenClass)}>
                {siteName}
            </span>
        </div>
    );
}
